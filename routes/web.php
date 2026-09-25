<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('reception.index');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('reception.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/reception', [ReceptionController::class, 'index'])->name('reception.index');
    Route::get('/reception/patient-by-phone', [ReceptionController::class, 'findPatientByPhone'])->name('reception.patient-by-phone');
    Route::post('/reception/search-patients', [ReceptionController::class, 'searchPatients'])->name('reception.search-patients');
    Route::post('/reception/visits', [ReceptionController::class, 'storeVisit'])->name('reception.visits.store');
    Route::put('/reception/visits/{visit}', [ReceptionController::class, 'updateVisit'])->name('reception.visits.update');
    Route::post('/reception/visits/{visit}/complete', [ReceptionController::class, 'completeVisit'])->name('reception.visits.complete');
    Route::post('/reception/visits/{visit}/cancel', [ReceptionController::class, 'cancelVisit'])->name('reception.visits.cancel');
    Route::post('/reception/visits/{visit}/payments', [ReceptionController::class, 'recordPayment'])->name('reception.visits.payment');
    Route::post('/reception/visits/{visit}/refund', [ReceptionController::class, 'recordRefund'])->name('reception.visits.refund');

    Route::post('/reception/shifts/start', [ReceptionController::class, 'startShift'])->name('reception.shifts.start');
    Route::post('/reception/shifts/close', [ReceptionController::class, 'closeShift'])->name('reception.shifts.close');

    Route::post('/reception/expenses', [ReceptionController::class, 'storeExpense'])->name('reception.expenses.store');
    Route::put('/reception/expenses/{expense}', [ReceptionController::class, 'updateExpense'])->name('reception.expenses.update');
    Route::delete('/reception/expenses/{expense}', [ReceptionController::class, 'deleteExpense'])->name('reception.expenses.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
