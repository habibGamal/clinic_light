<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use DomainException;

final class ShiftService
{
    public function getActiveShift(?int $userId = null): ?Shift
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return null;
        }

        return Shift::query()
            ->where('user_id', $userId)
            ->where('status', ShiftStatus::Open)
            ->latest('opened_at')
            ->first();
    }

    public function startShift(int $userId, float $openingBalance): Shift
    {
        $existingShift = Shift::query()
            ->where('user_id', $userId)
            ->where('status', ShiftStatus::Open)
            ->first();

        if ($existingShift) {
            throw new DomainException('توجد وردية مفتوحة بالفعل.');
        }

        return Shift::query()->create([
            'user_id' => $userId,
            'opening_balance' => $openingBalance,
            'opened_at' => now(),
            'status' => ShiftStatus::Open,
        ]);
    }

    public function closeShift(?Shift $activeShift, float $closingBalance): void
    {
        if (! $activeShift) {
            throw new DomainException('لا توجد وردية مفتوحة لإغلاقها.');
        }

        $activeShift->update([
            'closing_balance' => $closingBalance,
            'actual_cash' => $closingBalance,
            'difference' => 0,
            'closed_at' => now(),
            'status' => ShiftStatus::Closed,
        ]);
    }
}
