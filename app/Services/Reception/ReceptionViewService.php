<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PatientVisit;
use App\Models\ReferringDoctor;
use App\Models\Service;
use App\Models\Shift;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class ReceptionViewService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildIndexProps(Request $request, ?Shift $activeShift): array
    {
        $visits = $this->getVisitsPagination($request, $activeShift);
        $todayExpenses = $this->getTodayExpenses($activeShift);

        return [
            'visits' => $visits,
            'todayExpenses' => $todayExpenses,
            'todayExpensesTotal' => (float) $todayExpenses->sum('amount'),
            'services' => $this->getServices(),
            'referringDoctors' => ReferringDoctor::query()->orderBy('name')->get(['id', 'name', 'phone', 'specialization']),
            'expenseCategories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'activeShift' => $activeShift ? [
                'id' => $activeShift->id,
                'opened_at' => $activeShift->opened_at?->format('Y-m-d H:i'),
                'opening_balance' => (float) $activeShift->opening_balance,
            ] : null,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
                'date_filter' => $request->input('date_filter', 'today'),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $pm): array => [
                'value' => $pm->value,
                'label' => $pm->getLabel(),
            ])->all(),
            'genderOptions' => collect(Gender::cases())->map(fn (Gender $g): array => [
                'value' => $g->value,
                'label' => $g->getLabel(),
            ])->all(),
        ];
    }

    private function getVisitsPagination(Request $request, ?Shift $activeShift): LengthAwarePaginator
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $dateFilter = $request->input('date_filter', 'today');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $visitsQuery = PatientVisit::query()
            ->with([
                'patient',
                'referringDoctor',
                'invoice.items',
                'payments',
                'visitServices.service.serviceCategory',
                'visitServices.selectedOptions.serviceOption',
                'visitServices.reports.doctor',
                'reports.doctor',
                'reports.visitService.service',
            ])
            ->withCount('visitServices');

        if ($search) {
            $visitsQuery->where(function ($q) use ($search): void {
                $q->where('id', $search)
                    ->orWhereHas('patient', function ($pq) use ($search): void {
                        $pq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && $status !== 'all') {
            $visitsQuery->where('status', $status);
        }

        if ($dateFilter === 'today') {
            if ($activeShift) {
                $visitsQuery->where('shift_id', $activeShift->id);
            } else {
                $visitsQuery->whereDate('visit_date', Carbon::today());
            }
        } elseif ($dateFilter === 'custom') {
            if ($dateFrom) {
                $visitsQuery->whereDate('visit_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $visitsQuery->whereDate('visit_date', '<=', $dateTo);
            }
        }

        $visits = $visitsQuery
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Ensure each visit's invoice is synced
        $visits->each(fn (PatientVisit $visit) => $this->invoiceService->syncInvoice($visit));

        return $visits;
    }

    /**
     * @return Collection<int, Expense>
     */
    private function getTodayExpenses(?Shift $activeShift): Collection
    {
        $expensesQuery = Expense::query()->with(['category', 'creator:id,name', 'shift']);

        if ($activeShift) {
            $expensesQuery->where('shift_id', $activeShift->id);
        } else {
            $expensesQuery->whereDate('created_at', Carbon::today());
        }

        return $expensesQuery->latest('id')->get();
    }

    /**
     * @return Collection<int, Service>
     */
    private function getServices(): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->with([
                'serviceCategory:id,name',
                'optionGroups.options',
            ])
            ->orderBy('name')
            ->get();
    }
}
