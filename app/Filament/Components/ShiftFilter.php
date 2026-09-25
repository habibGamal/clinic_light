<?php

declare(strict_types=1);

namespace App\Filament\Components;

use App\Filament\Filters\ShiftFilter as TableShiftFilter;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;

final class ShiftFilter extends Section
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->heading('تصفية الورديات');
        $this->description('اختر ورديات محددة أو حدد فترة زمنية لاستخراج بيانات التقارير');
        $this->schema(TableShiftFilter::getFormComponents());
        $this->columns(2);
    }

    /**
     * Resolves shift IDs from filter/form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<int>|null
     */
    public static function resolveShiftIds(array $data): ?array
    {
        return TableShiftFilter::resolveShiftIds($data);
    }

    /**
     * Always returns an array of shift IDs (non-nullable).
     *
     * @param  array<string, mixed>  $data
     * @return array<int>
     */
    public static function getShiftIds(array $data, bool $defaultToAllIfUnfiltered = false): array
    {
        return TableShiftFilter::getShiftIds($data, $defaultToAllIfUnfiltered);
    }

    /**
     * Applies shift filter to query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<TModel>
     */
    public static function applyToQuery(Builder $query, array $data, string $column = 'shift_id'): Builder
    {
        return TableShiftFilter::applyToQuery($query, $data, $column);
    }
}
