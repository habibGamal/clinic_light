<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\VisitServiceStatus;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Shift;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use App\Services\InvoiceService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class VisitManagementService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeVisit(array $data, Shift $shift): PatientVisit
    {
        return DB::transaction(function () use ($data, $shift): PatientVisit {
            $patient = $this->resolveOrCreatePatient($data);

            $visit = PatientVisit::query()->create([
                'patient_id' => $patient->id,
                'referring_doctor_id' => $data['referring_doctor_id'] ?? null,
                'shift_id' => $shift->id,
                'visit_date' => $data['visit_date'] ?? now(),
                'status' => VisitStatus::Waiting,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['services'] as $item) {
                $this->createVisitServiceLine($visit, $item);
            }

            $invoice = $this->invoiceService->syncInvoice($visit);

            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            if ($paidAmount > 0) {
                $paymentMethod = $data['payment_method'] ?? PaymentMethod::Cash->value;
                Payment::query()->create([
                    'visit_id' => $visit->id,
                    'invoice_id' => $invoice->id,
                    'shift_id' => $shift->id,
                    'type' => 'payment',
                    'amount' => min($paidAmount, (float) $invoice->total_amount),
                    'payment_method' => $paymentMethod,
                    'paid_at' => now(),
                    'notes' => $data['payment_notes'] ?? 'دفعة تسجيل زيارة جديدة',
                ]);

                $this->invoiceService->syncInvoice($visit);
            }

            return $visit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVisit(PatientVisit $visit, array $data, ?Shift $activeShift): void
    {
        if (! $activeShift) {
            throw new DomainException('لا يمكن تعديل الزيارة بدون وجود وردية مفتوحة حالياً.');
        }

        if ($visit->status !== VisitStatus::Waiting) {
            throw new DomainException('لا يمكن تعديل هذه الزيارة لأنها ليست قيد الانتظار.');
        }

        $incomingServiceIds = collect($data['services'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $servicesToRemove = $visit->visitServices()->whereNotIn('id', $incomingServiceIds)->get();
        foreach ($servicesToRemove as $toRemove) {
            if ($toRemove->reports()->exists()) {
                throw new DomainException("لا يمكن حذف الفحص '{$toRemove->service?->name}' لوجود تقرير طبي مسجل له.");
            }
        }

        DB::transaction(function () use ($visit, $data, $servicesToRemove): void {
            $patient = $visit->patient;
            if ($patient) {
                $patient->update([
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'birth_date' => $data['birth_date'] ?? $patient->birth_date,
                    'gender' => $data['gender'] ?? $patient->gender,
                    'address' => $data['address'] ?? $patient->address,
                    'notes' => $data['patient_notes'] ?? $patient->notes,
                ]);
            }

            $visit->update([
                'referring_doctor_id' => $data['referring_doctor_id'] ?? null,
                'visit_date' => $data['visit_date'] ?? $visit->visit_date,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($servicesToRemove as $toRemove) {
                $toRemove->selectedOptions()->delete();
                $toRemove->delete();
            }

            foreach ($data['services'] as $item) {
                $this->upsertVisitServiceLine($visit, $item);
            }

            $this->invoiceService->syncInvoice($visit);
        });
    }

    public function completeVisit(PatientVisit $visit): void
    {
        if ($visit->hasDuePayments()) {
            throw new DomainException('لا يمكن إكمال الزيارة: توجد مبالغ مستحقة بقيمة '.number_format($visit->duePaymentAmount(), 2).' EGP. يرجى سداد المبلغ أولاً.');
        }

        $visit->update(['status' => VisitStatus::Completed]);
    }

    public function cancelVisit(PatientVisit $visit, ?Shift $activeShift): bool
    {
        if (! $activeShift) {
            throw new DomainException('لا يمكن إلغاء الزيارة بدون وجود وردية مفتوحة حالياً.');
        }

        if ($visit->status !== VisitStatus::Waiting) {
            throw new DomainException('لا يمكن إلغاء هذه الزيارة لأنها ليست قيد الانتظار.');
        }

        $hasPayments = $visit->payments()->where('amount', '>', 0)->exists();

        $visit->update(['status' => VisitStatus::Cancelled]);

        return $hasPayments;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOrCreatePatient(array $data): Patient
    {
        if (! empty($data['patient_id'])) {
            return Patient::query()->findOrFail($data['patient_id']);
        }

        $existing = Patient::query()->where('phone', $data['phone'])->first();
        if ($existing) {
            $existing->update([
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'] ?? $existing->birth_date,
                'gender' => $data['gender'] ?? $existing->gender,
                'address' => $data['address'] ?? $existing->address,
            ]);

            return $existing;
        }

        return Patient::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['patient_notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createVisitServiceLine(PatientVisit $visit, array $item): VisitService
    {
        $calculated = $this->calculateServicePricing($item);

        $visitService = VisitService::query()->create([
            'visit_id' => $visit->id,
            'service_id' => $calculated['service']->id,
            'quantity' => $calculated['quantity'],
            'unit_price' => $calculated['unit_price'],
            'discount_type' => $calculated['discount_type'],
            'discount_value' => $calculated['discount_value'],
            'subtotal' => $calculated['subtotal'],
            'total' => $calculated['total'],
            'status' => VisitServiceStatus::Pending,
        ]);

        $this->attachSelectedOptions($visitService, $calculated['selected_option_ids']);

        return $visitService;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertVisitServiceLine(PatientVisit $visit, array $item): VisitService
    {
        $calculated = $this->calculateServicePricing($item);
        $existingId = isset($item['id']) ? (int) $item['id'] : null;
        $visitService = $existingId ? $visit->visitServices()->find($existingId) : null;

        if ($visitService) {
            $visitService->update([
                'service_id' => $calculated['service']->id,
                'quantity' => $calculated['quantity'],
                'unit_price' => $calculated['unit_price'],
                'discount_type' => $calculated['discount_type'],
                'discount_value' => $calculated['discount_value'],
                'subtotal' => $calculated['subtotal'],
                'total' => $calculated['total'],
            ]);
        } else {
            $visitService = VisitService::query()->create([
                'visit_id' => $visit->id,
                'service_id' => $calculated['service']->id,
                'quantity' => $calculated['quantity'],
                'unit_price' => $calculated['unit_price'],
                'discount_type' => $calculated['discount_type'],
                'discount_value' => $calculated['discount_value'],
                'subtotal' => $calculated['subtotal'],
                'total' => $calculated['total'],
                'status' => VisitServiceStatus::Pending,
            ]);
        }

        $visitService->selectedOptions()->delete();
        $this->attachSelectedOptions($visitService, $calculated['selected_option_ids']);

        return $visitService;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{service: Service, quantity: int, unit_price: float, discount_type: string, discount_value: float, subtotal: float, total: float, selected_option_ids: array<int>}
     */
    private function calculateServicePricing(array $item): array
    {
        $service = Service::query()->with('optionGroups.options')->findOrFail($item['service_id']);
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = (float) $service->base_price;

        $selectedOptionIds = $item['selected_options'] ?? [];
        if (! empty($selectedOptionIds)) {
            $additionalTotal = (float) ServiceOption::query()->whereIn('id', $selectedOptionIds)->sum('additional_price');
            $unitPrice += $additionalTotal;
        }

        $subtotal = $unitPrice * $quantity;
        $discType = $item['discount_type'] ?? DiscountType::Fixed->value;
        $discVal = (float) ($item['discount_value'] ?? 0);
        $discAmount = ($discType === DiscountType::Percent->value || $discType === 'percent')
            ? ($subtotal * ($discVal / 100))
            : $discVal;
        $total = max(0.0, $subtotal - $discAmount);

        return [
            'service' => $service,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => $discType,
            'discount_value' => $discVal,
            'subtotal' => $subtotal,
            'total' => $total,
            'selected_option_ids' => $selectedOptionIds,
        ];
    }

    /**
     * @param  array<int>  $selectedOptionIds
     */
    private function attachSelectedOptions(VisitService $visitService, array $selectedOptionIds): void
    {
        foreach ($selectedOptionIds as $optId) {
            $opt = ServiceOption::query()->find($optId);
            if ($opt) {
                VisitServiceSelectedOption::query()->create([
                    'visit_service_id' => $visitService->id,
                    'service_option_id' => $opt->id,
                    'additional_price' => (float) $opt->additional_price,
                ]);
            }
        }
    }
}
