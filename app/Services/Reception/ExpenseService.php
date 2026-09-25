<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Models\Expense;
use App\Models\Shift;
use DomainException;

final class ExpenseService
{
    public function storeExpense(
        Shift $shift,
        int $userId,
        int $categoryId,
        float $amount,
        ?string $notes = null
    ): Expense {
        return Expense::query()->create([
            'expense_category_id' => $categoryId,
            'shift_id' => $shift->id,
            'amount' => $amount,
            'created_by' => $userId,
            'notes' => $notes,
        ]);
    }

    public function updateExpense(
        Expense $expense,
        ?Shift $activeShift,
        int $categoryId,
        float $amount,
        ?string $notes = null
    ): void {
        if (! $activeShift) {
            throw new DomainException('لا يمكن تعديل المصروف بدون وجود وردية مفتوحة حالياً.');
        }

        if ($expense->shift_id !== $activeShift->id || ! $expense->isEditable($activeShift)) {
            throw new DomainException('لا يمكن تعديل المصروف إلا ضمن نفس الوردية المفتوحة حالياً. بعد إغلاق الوردية لا يمكن تعديل أو حذف مصروفاتها.');
        }

        $expense->update([
            'expense_category_id' => $categoryId,
            'amount' => $amount,
            'notes' => $notes,
        ]);
    }

    public function deleteExpense(Expense $expense, ?Shift $activeShift): void
    {
        if (! $activeShift) {
            throw new DomainException('لا يمكن حذف المصروف بدون وجود وردية مفتوحة حالياً.');
        }

        if ($expense->shift_id !== $activeShift->id || ! $expense->isEditable($activeShift)) {
            throw new DomainException('لا يمكن حذف المصروف إلا ضمن نفس الوردية المفتوحة حالياً. بعد إغلاق الوردية لا يمكن تعديل أو حذف مصروفاتها.');
        }

        $expense->delete();
    }
}
