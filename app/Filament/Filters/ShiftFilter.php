<?php

declare(strict_types=1);

namespace App\Filament\Filters;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class ShiftFilter extends BaseFilter
{
    public const DEFAULT_NAME = 'shift_filter';

    protected string $shiftColumn = 'shift_id';

    protected ?string $relationshipName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تصفية حسب الورديات');

        $this->schema(self::getFormComponents());

        $this->columns(2);

        $this->indicateUsing(function (array $data): array {
            return static::formatIndicators($data);
        });

        $this->query(function (Builder $query, array $data): Builder {
            $shiftIds = static::resolveShiftIds($data);

            if ($shiftIds === null) {
                return $query;
            }

            if ($this->relationshipName !== null) {
                if (empty($shiftIds)) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas(
                    $this->relationshipName,
                    fn (Builder $relQuery): Builder => $relQuery->whereIn($this->shiftColumn, $shiftIds)
                );
            }

            if (empty($shiftIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn($this->shiftColumn, $shiftIds);
        });
    }

    public static function getDefaultName(): ?string
    {
        return self::DEFAULT_NAME;
    }

    /**
     * Form components for the Shift Filter schema.
     *
     * @return array<int, Component>
     */
    public static function getFormComponents(): array
    {
        return [
            ToggleButtons::make('mode')
                ->label('نظام اختيار الورديات')
                ->options([
                    'shifts' => 'تحديد ورديات معينة',
                    'period' => 'تحديد فترة زمنية',
                ])
                ->default(function (): string {
                    return Shift::query()->where('status', ShiftStatus::Open)->exists() ? 'shifts' : 'period';
                })
                ->inline()
                ->live()
                ->columnSpanFull(),

            Select::make('shift_ids')
                ->label('اختر الورديات')
                ->placeholder('اختر وردية واحدة أو أكثر...')
                ->options(fn (): array => self::getShiftOptions())
                ->default(function (): array {
                    $activeShift = Shift::query()
                        ->where('status', ShiftStatus::Open)
                        ->when(auth()->check(), fn ($q) => $q->orderByRaw('user_id = ? desc', [auth()->id()]))
                        ->latest('opened_at')
                        ->first();

                    return $activeShift ? [$activeShift->id] : [];
                })
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => ($get('mode') ?? (Shift::query()->where('status', ShiftStatus::Open)->exists() ? 'shifts' : 'period')) === 'shifts')
                ->live()
                ->columnSpanFull(),

            Select::make('preset')
                ->label('الفترة المحددة')
                ->placeholder('اختر فترة...')
                ->options([
                    'today' => 'اليوم (Today)',
                    'yesterday' => 'أمس (Yesterday)',
                    'this_week' => 'هذا الأسبوع (This Week)',
                    'this_month' => 'هذا الشهر (This Month)',
                    'this_year' => 'هذا العام (This Year)',
                    'custom' => 'فترة مخصصة (Custom Range)',
                ])
                ->default('today')
                ->visible(fn (Get $get): bool => ($get('mode') ?? (Shift::query()->where('status', ShiftStatus::Open)->exists() ? 'shifts' : 'period')) === 'period')
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    [$from, $to] = static::resolveDateRange($state);
                    if ($from !== null && $to !== null) {
                        $set('date_from', $from->toDateString());
                        $set('date_to', $to->toDateString());
                    }
                })
                ->columnSpanFull(),

            DatePicker::make('date_from')
                ->label('من تاريخ')
                ->visible(fn (Get $get): bool => ($get('mode') ?? (Shift::query()->where('status', ShiftStatus::Open)->exists() ? 'shifts' : 'period')) === 'period')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom'))
                ->columnSpan(1),

            DatePicker::make('date_to')
                ->label('إلى تاريخ')
                ->visible(fn (Get $get): bool => ($get('mode') ?? (Shift::query()->where('status', ShiftStatus::Open)->exists() ? 'shifts' : 'period')) === 'period')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom'))
                ->columnSpan(1),
        ];
    }

    /**
     * Helper to return the schema array for use in any form.
     *
     * @return array<int, Component>
     */
    public static function getFilterSchema(): array
    {
        return self::getFormComponents();
    }

    /**
     * Helper to wrap the filter components in a Section for forms or custom report pages.
     */
    public static function makeSection(?string $heading = 'تصفية الورديات'): Section
    {
        return Section::make($heading)
            ->description('اختر ورديات محددة أو حدد فترة زمنية لاستخراج بيانات التقارير')
            ->schema(self::getFormComponents())
            ->columns(2);
    }

    /**
     * Resolves an array of shift IDs based on the filter data.
     * Returns null if no shift filter criteria was applied.
     * Returns an empty array [] if criteria was applied but no shifts match.
     *
     * @param  array<string, mixed>  $data
     * @return array<int>|null
     */
    public static function resolveShiftIds(array $data): ?array
    {
        $mode = $data['mode'] ?? null;

        if ($mode === 'shifts') {
            if (! isset($data['shift_ids']) || empty($data['shift_ids'])) {
                return null;
            }

            return array_values(array_map('intval', (array) $data['shift_ids']));
        }

        if ($mode === 'period') {
            if (! empty($data['period_shift_ids'])) {
                return array_values(array_map('intval', (array) $data['period_shift_ids']));
            }

            $preset = $data['preset'] ?? null;
            $fromStr = ! empty($data['date_from']) ? (string) $data['date_from'] : null;
            $toStr = ! empty($data['date_to']) ? (string) $data['date_to'] : null;

            if ($preset === null && $fromStr === null && $toStr === null) {
                return null;
            }

            [$start, $end] = self::resolveDateRange($preset, $fromStr, $toStr);

            if ($start === null && $end === null) {
                return null;
            }

            $query = Shift::query();

            if ($start !== null) {
                $query->where('opened_at', '>=', $start);
            }

            if ($end !== null) {
                $query->where('opened_at', '<=', $end);
            }

            $ids = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

            return empty($ids) ? [] : $ids;
        }

        // Direct fallback: shift_ids passed without mode
        if (! empty($data['shift_ids'])) {
            return array_values(array_map('intval', (array) $data['shift_ids']));
        }

        // Single shift fallback
        if (! empty($data['shift_id'])) {
            return [(int) $data['shift_id']];
        }

        // Fallback: preset or dates passed without mode
        if (! empty($data['preset']) || ! empty($data['date_from']) || ! empty($data['date_to'])) {
            [$start, $end] = self::resolveDateRange(
                $data['preset'] ?? null,
                ! empty($data['date_from']) ? (string) $data['date_from'] : null,
                ! empty($data['date_to']) ? (string) $data['date_to'] : null
            );

            if ($start !== null || $end !== null) {
                $query = Shift::query();
                if ($start !== null) {
                    $query->where('opened_at', '>=', $start);
                }
                if ($end !== null) {
                    $query->where('opened_at', '<=', $end);
                }

                $ids = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

                return empty($ids) ? [] : $ids;
            }
        }

        return null;
    }

    /**
     * Always returns an array of shift IDs (non-nullable).
     *
     * @param  array<string, mixed>  $data
     * @return array<int>
     */
    public static function getShiftIds(array $data, bool $defaultToAllIfUnfiltered = false): array
    {
        $ids = self::resolveShiftIds($data);

        if ($ids === null) {
            return $defaultToAllIfUnfiltered
                ? Shift::query()->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [];
        }

        return $ids;
    }

    /**
     * Resolves start and end Carbon datetime instances from preset or custom dates.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function resolveDateRange(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = Carbon::now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom', null => [
                self::parseDate($from, isStart: true),
                self::parseDate($to, isStart: false),
            ],
            default => [
                self::parseDate($from, isStart: true),
                self::parseDate($to, isStart: false),
            ],
        };
    }

    /**
     * Applies the resolved shift filter to an Eloquent query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<TModel>
     */
    public static function applyToQuery(Builder $query, array $data, string $column = 'shift_id'): Builder
    {
        $shiftIds = self::resolveShiftIds($data);

        if ($shiftIds === null) {
            return $query;
        }

        if (empty($shiftIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $shiftIds);
    }

    /**
     * Generates human-readable indicators for Filament table filter badges.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, Indicator>
     */
    public static function formatIndicators(array $data): array
    {
        $indicators = [];
        $mode = $data['mode'] ?? null;

        if ($mode === 'shifts') {
            $ids = (array) ($data['shift_ids'] ?? []);
            if (! empty($ids)) {
                $count = count($ids);
                if ($count === 1) {
                    $shift = Shift::find(reset($ids));
                    $label = $shift ? "وردية #{$shift->id}" : 'وردية محددة';
                } else {
                    $label = "{$count} ورديات محددة";
                }

                $indicators[] = Indicator::make("الورديات: {$label}")
                    ->removeField('shift_ids');
            }
        } elseif ($mode === 'period') {
            $preset = $data['preset'] ?? 'custom';
            $presetLabels = [
                'today' => 'اليوم',
                'yesterday' => 'أمس',
                'this_week' => 'هذا الأسبوع',
                'this_month' => 'هذا الشهر',
                'this_year' => 'هذا العام',
                'custom' => 'مخصص',
            ];

            $from = $data['date_from'] ?? null;
            $to = $data['date_to'] ?? null;

            $desc = $presetLabels[$preset] ?? 'فترة مخصصة';
            if ($from || $to) {
                $desc .= ' ('.($from ?? '...').' إلى '.($to ?? '...').')';
            }

            $indicators[] = Indicator::make("فترة الورديات: {$desc}");
        }

        return $indicators;
    }

    /**
     * Formatted list of all recent shifts for select options.
     *
     * @return array<int, string>
     */
    public static function getShiftOptions(): array
    {
        return Shift::query()
            ->with('user')
            ->latest('id')
            ->take(150)
            ->get()
            ->mapWithKeys(fn (Shift $s): array => [
                $s->id => sprintf(
                    'وردية #%d - %s (%s) [%s]',
                    $s->id,
                    $s->user?->name ?? 'غير معروف',
                    $s->opened_at ? $s->opened_at->format('Y-m-d h:i A') : '-',
                    $s->status === ShiftStatus::Open ? '🟢 مفتوحة' : '⚪ مغلقة'
                ),
            ])
            ->all();
    }

    /**
     * Formatted list of shifts filtered to a specific period.
     *
     * @return array<int, string>
     */
    public static function getShiftOptionsForPeriod(?string $preset, ?string $from, ?string $to): array
    {
        [$start, $end] = self::resolveDateRange($preset, $from, $to);

        $query = Shift::query()->with('user')->latest('id');

        if ($start !== null) {
            $query->where('opened_at', '>=', $start);
        }

        if ($end !== null) {
            $query->where('opened_at', '<=', $end);
        }

        return $query->take(150)
            ->get()
            ->mapWithKeys(fn (Shift $s): array => [
                $s->id => sprintf(
                    'وردية #%d - %s (%s) [%s]',
                    $s->id,
                    $s->user?->name ?? 'غير معروف',
                    $s->opened_at ? $s->opened_at->format('Y-m-d h:i A') : '-',
                    $s->status === ShiftStatus::Open ? '🟢 مفتوحة' : '⚪ مغلقة'
                ),
            ])
            ->all();
    }

    public function shiftColumn(string $column): static
    {
        $this->shiftColumn = $column;

        return $this;
    }

    public function getShiftColumn(): string
    {
        return $this->shiftColumn;
    }

    public function relationship(string $relationshipName, string $column = 'shift_id'): static
    {
        $this->relationshipName = $relationshipName;
        $this->shiftColumn = $column;

        return $this;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationshipName;
    }

    /**
     * Safely parse a date string.
     */
    protected static function parseDate(?string $date, bool $isStart): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);

            return $isStart ? $parsed->startOfDay() : $parsed->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
