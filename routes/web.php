<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ScheduleBlockController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkBlockController;
use Illuminate\Support\Facades\Route;

Route::pattern('date', '\d{4}-\d{2}-\d{2}');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [CalendarController::class, 'show'])->name('calendar.show');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::get('/schedule', [ScheduleBlockController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleBlockController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule/{scheduleBlock}', [ScheduleBlockController::class, 'destroy'])->name('schedule.destroy');

    Route::post('/days/{date}/blocks', [WorkBlockController::class, 'store'])->name('work-blocks.store');
    Route::put('/days/{date}/scheduled/{scheduleBlock}', [WorkBlockController::class, 'updateScheduled'])->name('work-blocks.update-scheduled');
    Route::delete('/days/{date}/scheduled/{scheduleBlock}', [WorkBlockController::class, 'destroyScheduled'])->name('work-blocks.destroy-scheduled');
    Route::delete('/days/{date}', [WorkBlockController::class, 'reset'])->name('work-days.reset');
    Route::put('/blocks/{workBlock}', [WorkBlockController::class, 'update'])->name('work-blocks.update');
    Route::delete('/blocks/{workBlock}', [WorkBlockController::class, 'destroy'])->name('work-blocks.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
    Route::put('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
});
