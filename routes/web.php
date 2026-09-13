<?php

use App\Http\Controllers\AssignedTripController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BudgetItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FinanceEntryController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\TourLeaderActivationController;
use App\Http\Controllers\TourLeaderController;
use App\Http\Controllers\TourLeaderPasswordController;
use App\Http\Controllers\TripAssignmentResponseController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripFinanceController;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::bind('tourLeader', fn (string $value): User => User::query()
    ->where('role', UserRole::TourLeader)
    ->findOrFail($value));

Route::get('/', fn (Request $request): RedirectResponse => redirect()->route(
    $request->user() === null ? 'login' : 'home',
));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/home', fn (Request $request): RedirectResponse => redirect()->route(
        $request->user()->role->homeRouteName(),
    ))->name('home');

    Route::prefix('super-admin')
        ->name('super-admin.')
        ->middleware('role:'.UserRole::SuperAdmin->value)
        ->group(function (): void {
            Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('index');

            Route::get('finance', [FinanceReportController::class, 'index'])->name('finance.index');
            Route::get('finance/export', [FinanceReportController::class, 'export'])->name('finance.export');
            Route::get('trips/{trip}/finance', [TripFinanceController::class, 'show'])->name('trips.finance.show');
            Route::put('trips/{trip}/finance', [TripFinanceController::class, 'update'])->name('trips.finance.update');
            Route::post('trips/{trip}/entries', [FinanceEntryController::class, 'store'])->name('trips.entries.store');
            Route::patch('trips/{trip}/entries/{entry}', [FinanceEntryController::class, 'update'])->name('trips.entries.update');
            Route::post('trips/{trip}/budgets', [BudgetItemController::class, 'store'])->name('trips.budgets.store');
            Route::put('trips/{trip}/budgets/{budgetItem}', [BudgetItemController::class, 'update'])->name('trips.budgets.update');
            Route::delete('trips/{trip}/budgets/{budgetItem}', [BudgetItemController::class, 'destroy'])->name('trips.budgets.destroy');
            Route::post('trips/{trip}/settlement', [SettlementController::class, 'store'])->name('trips.settlement.store');
            Route::delete('trips/{trip}/settlement', [SettlementController::class, 'destroy'])->name('trips.settlement.destroy');

            Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
            Route::resource('trips', TripController::class)->except('destroy');

            Route::resource('tour-leaders', TourLeaderController::class)
                ->parameters(['tour-leaders' => 'tourLeader'])
                ->except(['show', 'destroy']);

            Route::post('tour-leaders/{tourLeader}/activation', [TourLeaderActivationController::class, 'store'])
                ->name('tour-leaders.activation.store');
            Route::delete('tour-leaders/{tourLeader}/activation', [TourLeaderActivationController::class, 'destroy'])
                ->name('tour-leaders.activation.destroy');
            Route::put('tour-leaders/{tourLeader}/password', [TourLeaderPasswordController::class, 'update'])
                ->name('tour-leaders.password.update');
        });

    Route::get('/tour-leader', [AssignedTripController::class, 'index'])
        ->middleware('role:'.UserRole::TourLeader->value)
        ->name('tour-leader.index');

    Route::get('/tour-leader/trips/{trip}', [AssignedTripController::class, 'show'])
        ->middleware('role:'.UserRole::TourLeader->value)
        ->name('tour-leader.trips.show');

    Route::post('/tour-leader/assignments/{assignment}/accept', [TripAssignmentResponseController::class, 'store'])
        ->middleware('role:'.UserRole::TourLeader->value)
        ->name('tour-leader.assignments.accept');
    Route::delete('/tour-leader/assignments/{assignment}/reject', [TripAssignmentResponseController::class, 'destroy'])
        ->middleware('role:'.UserRole::TourLeader->value)
        ->name('tour-leader.assignments.reject');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/tour-leader/trips/{trip}/finance', [TripFinanceController::class, 'show'])
        ->middleware('role:'.UserRole::TourLeader->value)->name('tour-leader.trips.finance');
    Route::post('/tour-leader/trips/{trip}/expenses', [FinanceEntryController::class, 'store'])
        ->middleware('role:'.UserRole::TourLeader->value)->name('tour-leader.trips.expenses.store');
    Route::get('/trips/{trip}/entries/{entry}/receipt', [FinanceEntryController::class, 'receipt'])->name('entries.receipt');
});
