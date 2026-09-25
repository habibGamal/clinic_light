<?php

declare(strict_types=1);

use App\Filament\Components\ShiftFilter as ShiftFilterComponent;
use App\Filament\Filters\ShiftFilter;
use App\Models\Expense;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can instantiate ShiftFilter table filter and schema component', function () {
    $filter = ShiftFilter::make();

    expect($filter)->toBeInstanceOf(ShiftFilter::class)
        ->and($filter->getName())->toBe('shift_filter')
        ->and($filter->getLabel())->toBe('تصفية حسب الورديات');

    $schema = ShiftFilter::getFilterSchema();
    expect($schema)->toBeArray()
        ->and(count($schema))->toBeGreaterThanOrEqual(5);

    $component = ShiftFilterComponent::make();
    expect($component)->toBeInstanceOf(ShiftFilterComponent::class)
        ->and($component->getHeading())->toBe('تصفية الورديات');
});

it('resolves shift IDs in shifts mode', function () {
    $shift1 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);
    $shift2 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);
    $shift3 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'shifts',
        'shift_ids' => [(string) $shift1->id, (string) $shift3->id],
    ]);

    expect($resolved)->toBe([$shift1->id, $shift3->id]);
});

it('returns null if shifts mode has no shifts selected', function () {
    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'shifts',
        'shift_ids' => [],
    ]);

    expect($resolved)->toBeNull();
});

it('resolves shifts in period mode with today preset', function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $todayShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-09-25 09:30:00'),
    ]);

    $yesterdayShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-09-24 18:00:00'),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'today',
    ]);

    expect($resolved)->toContain($todayShift->id)
        ->and($resolved)->not->toContain($yesterdayShift->id);

    Carbon::setTestNow();
});

it('resolves shifts in period mode with yesterday preset', function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $todayShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-09-25 09:30:00'),
    ]);

    $yesterdayShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-09-24 18:00:00'),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'yesterday',
    ]);

    expect($resolved)->toContain($yesterdayShift->id)
        ->and($resolved)->not->toContain($todayShift->id);

    Carbon::setTestNow();
});

it('resolves shifts in period mode with this_week preset', function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $inWeekShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::now()->startOfWeek()->addHours(5),
    ]);

    $lastWeekShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::now()->startOfWeek()->subDays(2),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'this_week',
    ]);

    expect($resolved)->toContain($inWeekShift->id)
        ->and($resolved)->not->toContain($lastWeekShift->id);

    Carbon::setTestNow();
});

it('resolves shifts in period mode with this_month preset', function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $inMonthShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-09-05 10:00:00'),
    ]);

    $lastMonthShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-08-20 10:00:00'),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'this_month',
    ]);

    expect($resolved)->toContain($inMonthShift->id)
        ->and($resolved)->not->toContain($lastMonthShift->id);

    Carbon::setTestNow();
});

it('resolves shifts in period mode with this_year preset', function () {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $thisYearShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-03-15 10:00:00'),
    ]);

    $lastYearShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2025-11-20 10:00:00'),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'this_year',
    ]);

    expect($resolved)->toContain($thisYearShift->id)
        ->and($resolved)->not->toContain($lastYearShift->id);

    Carbon::setTestNow();
});

it('resolves shifts in period mode with custom date range', function () {
    $shift1 = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-06-10 12:00:00'),
    ]);

    $shift2 = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-06-25 12:00:00'),
    ]);

    $shiftOut = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-07-01 12:00:00'),
    ]);

    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'custom',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ]);

    expect($resolved)->toContain($shift1->id)
        ->and($resolved)->toContain($shift2->id)
        ->and($resolved)->not->toContain($shiftOut->id);
});

it('allows custom shift selection within a period', function () {
    $shift1 = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-06-10 12:00:00'),
    ]);

    $shift2 = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => Carbon::parse('2026-06-20 12:00:00'),
    ]);

    // Period includes both, but user picked shift2 only
    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'custom',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'period_shift_ids' => [(string) $shift2->id],
    ]);

    expect($resolved)->toBe([$shift2->id]);
});

it('returns empty array when period has no matching shifts', function () {
    $resolved = ShiftFilter::resolveShiftIds([
        'mode' => 'period',
        'preset' => 'custom',
        'date_from' => '1990-01-01',
        'date_to' => '1990-01-02',
    ]);

    expect($resolved)->toBe([]);
});

it('applies shift filter to an Eloquent query correctly', function () {
    $shift1 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);
    $shift2 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);

    $exp1 = Expense::factory()->create(['shift_id' => $shift1->id, 'amount' => 100]);
    $exp2 = Expense::factory()->create(['shift_id' => $shift2->id, 'amount' => 200]);

    // Filter to shift1
    $query1 = Expense::query();
    ShiftFilter::applyToQuery($query1, ['mode' => 'shifts', 'shift_ids' => [$shift1->id]]);
    $results1 = $query1->pluck('id')->all();

    expect($results1)->toBe([$exp1->id]);

    // Unfiltered query leaves all results
    $queryUnfiltered = Expense::query();
    ShiftFilter::applyToQuery($queryUnfiltered, []);
    expect($queryUnfiltered->count())->toBe(2);

    // Empty period query matches 0 results
    $queryEmpty = Expense::query();
    ShiftFilter::applyToQuery($queryEmpty, [
        'mode' => 'period',
        'preset' => 'custom',
        'date_from' => '1990-01-01',
        'date_to' => '1990-01-02',
    ]);
    expect($queryEmpty->count())->toBe(0);
});

it('provides getShiftIds helper with defaultToAllIfUnfiltered option', function () {
    $shift1 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);
    $shift2 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);

    // Filtered
    $ids = ShiftFilter::getShiftIds(['mode' => 'shifts', 'shift_ids' => [$shift1->id]]);
    expect($ids)->toBe([$shift1->id]);

    // Unfiltered, defaultToAll = false
    expect(ShiftFilter::getShiftIds([]))->toBe([]);

    // Unfiltered, defaultToAll = true
    $allIds = ShiftFilter::getShiftIds([], defaultToAllIfUnfiltered: true);
    expect($allIds)->toContain($shift1->id)->and($allIds)->toContain($shift2->id);
});

it('generates appropriate filter indicators for table header', function () {
    $shift = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);

    $indicatorsShifts = ShiftFilter::formatIndicators([
        'mode' => 'shifts',
        'shift_ids' => [$shift->id],
    ]);
    expect($indicatorsShifts)->toHaveCount(1)
        ->and($indicatorsShifts[0]->getLabel())->toContain((string) $shift->id);

    $indicatorsPeriod = ShiftFilter::formatIndicators([
        'mode' => 'period',
        'preset' => 'today',
    ]);
    expect($indicatorsPeriod)->toHaveCount(1)
        ->and($indicatorsPeriod[0]->getLabel())->toContain('اليوم');
});

it('supports custom column name and relationship filtering in query callback', function () {
    $shift1 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);
    $shift2 = Shift::factory()->create(['user_id' => $this->user->id, 'opened_at' => now()]);

    $patient1 = App\Models\Patient::factory()->create();
    $patient2 = App\Models\Patient::factory()->create();

    App\Models\PatientVisit::factory()->create([
        'patient_id' => $patient1->id,
        'shift_id' => $shift1->id,
    ]);

    App\Models\PatientVisit::factory()->create([
        'patient_id' => $patient2->id,
        'shift_id' => $shift2->id,
    ]);

    $filter = ShiftFilter::make('patient_shift_filter')
        ->relationship('visits', 'shift_id');

    expect($filter->getRelationshipName())->toBe('visits')
        ->and($filter->getShiftColumn())->toBe('shift_id');

    // Simulate query execution via apply()
    $query = App\Models\Patient::query();
    $filter->apply($query, [
        'mode' => 'shifts',
        'shift_ids' => [$shift1->id],
    ]);

    $results = $query->pluck('id')->all();
    expect($results)->toContain($patient1->id)
        ->and($results)->not->toContain($patient2->id);
});
