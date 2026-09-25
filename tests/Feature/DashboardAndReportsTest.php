<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Widgets\ClinicStatsOverviewWidget;
use App\Filament\Widgets\IncomeExpensesChartWidget;
use App\Filament\Widgets\TodayQueueTableWidget;
use App\Filament\Widgets\VisitsTrendChartWidget;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Shift;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render clinic stats overview widget', function () {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'visit_date' => now(),
        'status' => VisitStatus::Waiting,
    ]);

    livewire(ClinicStatsOverviewWidget::class)
        ->assertOk();
});

it('can render visits trend chart widget', function () {
    livewire(VisitsTrendChartWidget::class)
        ->assertOk();
});

it('can render income vs expenses chart widget', function () {
    livewire(IncomeExpensesChartWidget::class)
        ->assertOk();
});

it('can render today queue table widget', function () {
    $patient = Patient::factory()->create(['full_name' => 'محمد أحمد']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'visit_date' => now(),
        'status' => VisitStatus::Waiting,
    ]);

    livewire(TodayQueueTableWidget::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$visit]);
});
