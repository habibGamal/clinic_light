<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Reception\CloseShiftRequest;
use App\Http\Requests\Reception\RecordPaymentRequest;
use App\Http\Requests\Reception\RecordRefundRequest;
use App\Http\Requests\Reception\StartShiftRequest;
use App\Http\Requests\Reception\StoreExpenseRequest;
use App\Http\Requests\Reception\StoreVisitRequest;
use App\Http\Requests\Reception\UpdateExpenseRequest;
use App\Http\Requests\Reception\UpdateVisitRequest;
use App\Models\Expense;
use App\Models\PatientVisit;
use App\Services\Reception\ExpenseService;
use App\Services\Reception\PatientSearchService;
use App\Services\Reception\ReceptionViewService;
use App\Services\Reception\VisitManagementService;
use App\Services\Reception\VisitPaymentService;
use App\Services\ShiftService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ReceptionController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly PatientSearchService $patientSearchService,
        private readonly VisitManagementService $visitManagementService,
        private readonly VisitPaymentService $visitPaymentService,
        private readonly ExpenseService $expenseService,
        private readonly ReceptionViewService $receptionViewService
    ) {}

    public function index(Request $request): Response
    {
        $activeShift = $this->shiftService->getActiveShift();
        $props = $this->receptionViewService->buildIndexProps($request, $activeShift);

        return Inertia::render('Reception/Index', $props);
    }

    public function findPatientByPhone(Request $request): JsonResponse
    {
        $result = $this->patientSearchService->findByPhone($request->query('phone'));

        return response()->json($result);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $patients = $this->patientSearchService->search(
            query: $request->input('query'),
            phone: $request->input('phone'),
            name: $request->input('name')
        );

        return response()->json(['patients' => $patients]);
    }

    public function storeVisit(StoreVisitRequest $request): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();

        if (! $activeShift) {
            throw ValidationException::withMessages([
                'shift' => 'لا يمكن تسجيل زيارة بدون وجود وردية مفتوحة. يرجى فتح وردية أولاً.',
            ]);
        }

        $visit = $this->visitManagementService->storeVisit($request->validated(), $activeShift);

        return redirect()->route('reception.index')
            ->with('success', 'تم تسجيل زيارة المريض بنجاح!')
            ->with('new_visit_id', $visit->id);
    }

    public function updateVisit(UpdateVisitRequest $request, PatientVisit $visit): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();

        try {
            $this->visitManagementService->updateVisit($visit, $request->validated(), $activeShift);

            return redirect()->route('reception.index')
                ->with('success', 'تم تعديل بيانات الزيارة والفحوصات بنجاح!');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function completeVisit(PatientVisit $visit): RedirectResponse
    {
        try {
            $this->visitManagementService->completeVisit($visit);

            return back()->with('success', 'تم إكمال الزيارة بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelVisit(PatientVisit $visit): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();

        try {
            $hasPayments = $this->visitManagementService->cancelVisit($visit, $activeShift);

            $message = $hasPayments
                ? 'تم إلغاء الزيارة واسترداد المدفوعات تلقائياً.'
                : 'تم إلغاء الزيارة بنجاح.';

            return back()->with('success', $message);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function recordPayment(RecordPaymentRequest $request, PatientVisit $visit): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();
        $validated = $request->validated();

        try {
            $this->visitPaymentService->recordPayment(
                visit: $visit,
                activeShift: $activeShift,
                amount: (float) $validated['amount'],
                paymentMethod: $validated['payment_method'],
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', 'تم تسجيل الدفعة بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function recordRefund(RecordRefundRequest $request, PatientVisit $visit): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();
        $validated = $request->validated();

        try {
            $this->visitPaymentService->recordRefund(
                visit: $visit,
                activeShift: $activeShift,
                amount: (float) $validated['amount'],
                paymentMethod: $validated['payment_method'],
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', 'تم تسجيل استرداد المبلغ بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeExpense(StoreExpenseRequest $request): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();

        if (! $activeShift) {
            throw ValidationException::withMessages([
                'shift' => 'لا يمكن تسجيل مصروف بدون وجود وردية مفتوحة. يرجى فتح وردية أولاً.',
            ]);
        }

        $validated = $request->validated();
        $this->expenseService->storeExpense(
            shift: $activeShift,
            userId: (int) (auth()->id() ?? 1),
            categoryId: (int) $validated['expense_category_id'],
            amount: (float) $validated['amount'],
            notes: $validated['notes'] ?? null
        );

        return back()->with('success', 'تم تسجيل المصروف بنجاح.');
    }

    public function updateExpense(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();
        $validated = $request->validated();

        try {
            $this->expenseService->updateExpense(
                expense: $expense,
                activeShift: $activeShift,
                categoryId: (int) $validated['expense_category_id'],
                amount: (float) $validated['amount'],
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', 'تم تعديل المصروف بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deleteExpense(Expense $expense): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();

        try {
            $this->expenseService->deleteExpense($expense, $activeShift);

            return back()->with('success', 'تم حذف المصروف بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function startShift(StartShiftRequest $request): RedirectResponse
    {
        $userId = (int) auth()->id();
        $validated = $request->validated();

        try {
            $this->shiftService->startShift($userId, (float) $validated['opening_balance']);

            return back()->with('success', 'تم فتح الوردية بنجاح!');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function closeShift(CloseShiftRequest $request): RedirectResponse
    {
        $activeShift = $this->shiftService->getActiveShift();
        $validated = $request->validated();

        try {
            $this->shiftService->closeShift($activeShift, (float) $validated['closing_balance']);

            return back()->with('success', 'تم إغلاق الوردية بنجاح!');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
