<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Academic\AcademicPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ProfileController;
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
    Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('portal', PortalController::class)->middleware('permission:portal.view')->name('portal');
    Route::get('reports', ReportsController::class)->middleware('permission:reports.view')->name('reports');
    Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

    Route::prefix('academic')->name('academic.')->group(function (): void {
        Route::get('students', [AcademicPageController::class, 'students'])->name('students.index');
        Route::get('students/create', [AcademicPageController::class, 'createStudent'])->name('students.create');
        Route::post('students', [AcademicPageController::class, 'storeStudent'])->name('students.store');
        Route::post('students/import', [AcademicPageController::class, 'importStudents'])->name('students.import');
        Route::get('students/{student}', [AcademicPageController::class, 'showStudent'])->name('students.show');
        Route::get('students/{student}/edit', [AcademicPageController::class, 'editStudent'])->name('students.edit');
        Route::put('students/{student}', [AcademicPageController::class, 'updateStudent'])->name('students.update');
        Route::delete('students/{student}', [AcademicPageController::class, 'destroyStudent'])->name('students.destroy');
        Route::post('students/{student}/guardians', [AcademicPageController::class, 'linkGuardian'])->name('students.guardians.store');
        Route::delete('students/{student}/guardians/{guardian}', [AcademicPageController::class, 'unlinkGuardian'])->name('students.guardians.destroy');
        Route::post('students/{student}/enrollments', [AcademicPageController::class, 'enrollStudent'])->name('students.enrollments.store');
        Route::post('students/{student}/transfer', [AcademicPageController::class, 'transferStudent'])->name('students.transfer');

        Route::get('teachers', [AcademicPageController::class, 'teachers'])->name('teachers.index');
        Route::post('teachers', [AcademicPageController::class, 'storeTeacher'])->name('teachers.store');
        Route::put('teachers/{teacher}', [AcademicPageController::class, 'updateTeacher'])->name('teachers.update');
        Route::delete('teachers/{teacher}', [AcademicPageController::class, 'destroyTeacher'])->name('teachers.destroy');

        Route::get('classes', [AcademicPageController::class, 'classes'])->name('classes.index');
        Route::post('classes', [AcademicPageController::class, 'storeClass'])->name('classes.store');
        Route::get('classes/{schoolClass}', [AcademicPageController::class, 'showClass'])->name('classes.show');
        Route::put('classes/{schoolClass}', [AcademicPageController::class, 'updateClass'])->name('classes.update');
        Route::delete('classes/{schoolClass}', [AcademicPageController::class, 'destroyClass'])->name('classes.destroy');
        Route::put('classes/{schoolClass}/homeroom', [AcademicPageController::class, 'assignHomeroom'])->name('classes.homeroom');
        Route::post('classes/{schoolClass}/teaching-assignments', [AcademicPageController::class, 'storeTeachingAssignment'])->name('classes.teaching-assignments.store');
        Route::delete('teaching-assignments/{teachingAssignment}', [AcademicPageController::class, 'destroyTeachingAssignment'])->name('teaching-assignments.destroy');
        Route::get('classes/{schoolClass}/students/export', [AcademicPageController::class, 'exportClassStudents'])->name('classes.students.export');
    });

    Route::prefix('manage/{resource}')
        ->whereIn('resource', array_keys(config('school.resources')))
        ->group(function (): void {
            Route::get('/', [ResourceController::class, 'index'])->middleware('resource.permission:view')->name('resources.index');
            Route::post('/', [ResourceController::class, 'store'])->middleware('resource.permission:create')->name('resources.store');
            Route::put('/{id}', [ResourceController::class, 'update'])->middleware('resource.permission:update')->name('resources.update');
            Route::delete('/{id}', [ResourceController::class, 'destroy'])->middleware('resource.permission:delete')->name('resources.destroy');
        });
});
