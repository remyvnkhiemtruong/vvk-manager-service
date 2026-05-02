<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('portal', PortalController::class)->name('portal');
    Route::get('reports', ReportsController::class)->name('reports');
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::prefix('manage/{resource}')
        ->whereIn('resource', array_keys(config('school.resources')))
        ->group(function (): void {
            Route::get('/', [ResourceController::class, 'index'])->name('resources.index');
            Route::post('/', [ResourceController::class, 'store'])->name('resources.store');
            Route::put('/{id}', [ResourceController::class, 'update'])->name('resources.update');
            Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy');
        });
});

