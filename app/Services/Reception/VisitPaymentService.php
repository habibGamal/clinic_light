<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Enums\VisitStatus;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Shift;
use App\Services\InvoiceService;
use DomainException;

final class VisitPaymentService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function recordPayment(
        PatientVisit $visit,
        ?Shift $activeShift,
        float $amount,
        string $paymentMethod,
        ?string $notes = null
    ): Payment {
        if (! $activeShift) {
            throw new DomainException('لا يمكن تسجيل دفعة بدون وجود وردية مفتوحة حالياً.');
        }

        if ($visit->status === VisitStatus::Cancelled) {
            throw new DomainException('لا يمكن تسجيل دفعة لزيارة ملغاة.');
        }

        $invoice = $this->invoiceService->getOrCreateInvoice($visit);
        $due = (float) $invoice->remaining_amount;

        if ($amount > $due) {
            throw new DomainException("المبلغ المدخل ({$amount} EGP) يتجاوز المتبقي المستحق ({$due} EGP).");
        }

        $payment = Payment::query()->create([
            'visit_id' => $visit->id,
            'invoice_id' => $invoice->id,
            'shift_id' => $activeShift->id,
            'type' => 'payment',
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'notes' => $notes ?? 'تحصيل دفعة من الاستقبال',
        ]);

        $this->invoiceService->syncInvoice($visit);

        return $payment;
    }

    public function recordRefund(
        PatientVisit $visit,
        ?Shift $activeShift,
        float $amount,
        string $paymentMethod,
        ?string $notes = null
    ): Payment {
        if (! $activeShift) {
            throw new DomainException('لا يمكن استرداد المبالغ بدون وجود وردية مفتوحة حالياً.');
        }

        $netPaid = (float) $visit->payments()->sum('amount');

        if ($amount > $netPaid) {
            throw new DomainException("المبلغ المراد استرداده ({$amount} EGP) يتجاوز صافي المدفوعات المسجلة ({$netPaid} EGP).");
        }

        $invoice = $this->invoiceService->getOrCreateInvoice($visit);

        $payment = Payment::query()->create([
            'visit_id' => $visit->id,
            'invoice_id' => $invoice->id,
            'shift_id' => $activeShift->id,
            'type' => 'refund',
            'amount' => -abs($amount),
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'notes' => $notes ?? 'استرداد دفعة من الاستقبال',
        ]);

        $this->invoiceService->syncInvoice($visit);

        return $payment;
    }
}
