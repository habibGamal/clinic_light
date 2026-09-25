<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Concerns;

use App\Enums\ShiftStatus;
use App\Filament\Filters\ShiftFilter;
use App\Models\Shift;

trait HasSelectedShifts
{
    /**
     * @return array<int>
     */
    protected function getSelectedShiftIds(): array
    {
        $filters = $this->pageFilters ?? [];

        $shiftIds = ShiftFilter::resolveShiftIds($filters);

        if ($shiftIds !== null) {
            return $shiftIds;
        }

        // Direct fallback: if shift_ids was passed without mode or in direct format
        if (! empty($filters['shift_ids'])) {
            return array_values(array_filter(array_map('intval', (array) $filters['shift_ids'])));
        }

        if (! empty($filters['shift_id'])) {
            return [(int) $filters['shift_id']];
        }

        // Auto select currently opened shift if exists
        $activeShift = Shift::query()
            ->where('status', ShiftStatus::Open)
            ->when(auth()->check(), fn ($q) => $q->orderByRaw('user_id = ? desc', [auth()->id()]))
            ->latest('opened_at')
            ->first();

        if ($activeShift) {
            return [$activeShift->id];
        }

        $latest = Shift::query()->latest('opened_at')->first();

        return $latest ? [$latest->id] : [];
    }
}
