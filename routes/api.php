<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Academic\AcademicApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('api.auth.refresh');

    Route::middleware('jwt.auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('profile', [AuthController::class, 'profile'])->name('api.auth.profile');
    });
});

Route::middleware('jwt.auth')->prefix('academic')->group(function (): void {
    Route::get('students', [AcademicApiController::class, 'students']);
    Route::post('students', [AcademicApiController::class, 'storeStudent']);
    Route::post('students/import', [AcademicApiController::class, 'importStudents']);
    Route::get('students/{student}', [AcademicApiController::class, 'showStudent']);
    Route::put('students/{student}', [AcademicApiController::class, 'updateStudent']);
    Route::delete('students/{student}', [AcademicApiController::class, 'destroyStudent']);
    Route::post('students/{student}/guardians', [AcademicApiController::class, 'linkGuardian']);
    Route::delete('students/{student}/guardians/{guardian}', [AcademicApiController::class, 'unlinkGuardian']);
    Route::post('students/{student}/enrollments', [AcademicApiController::class, 'enrollStudent']);
    Route::post('students/{student}/transfer', [AcademicApiController::class, 'transferStudent']);

    Route::get('guardians', [AcademicApiController::class, 'guardians']);
    Route::post('guardians', [AcademicApiController::class, 'storeGuardian']);
    Route::put('guardians/{guardian}', [AcademicApiController::class, 'updateGuardian']);
    Route::delete('guardians/{guardian}', [AcademicApiController::class, 'destroyGuardian']);

    Route::get('teachers', [AcademicApiController::class, 'teachers']);
    Route::post('teachers', [AcademicApiController::class, 'storeTeacher']);
    Route::put('teachers/{teacher}', [AcademicApiController::class, 'updateTeacher']);
    Route::delete('teachers/{teacher}', [AcademicApiController::class, 'destroyTeacher']);

    Route::get('school-years', [AcademicApiController::class, 'schoolYears']);
    Route::post('school-years', [AcademicApiController::class, 'storeSchoolYear']);
    Route::put('school-years/{schoolYear}', [AcademicApiController::class, 'updateSchoolYear']);
    Route::delete('school-years/{schoolYear}', [AcademicApiController::class, 'destroySchoolYear']);

    Route::get('semesters', [AcademicApiController::class, 'semesters']);
    Route::post('semesters', [AcademicApiController::class, 'storeSemester']);
    Route::put('semesters/{semester}', [AcademicApiController::class, 'updateSemester']);
    Route::delete('semesters/{semester}', [AcademicApiController::class, 'destroySemester']);

    Route::get('grades', [AcademicApiController::class, 'grades']);
    Route::post('grades', [AcademicApiController::class, 'storeGrade']);
    Route::put('grades/{grade}', [AcademicApiController::class, 'updateGrade']);
    Route::delete('grades/{grade}', [AcademicApiController::class, 'destroyGrade']);

    Route::get('classes', [AcademicApiController::class, 'classes']);
    Route::post('classes', [AcademicApiController::class, 'storeClass']);
    Route::get('classes/{schoolClass}', [AcademicApiController::class, 'showClass']);
    Route::put('classes/{schoolClass}', [AcademicApiController::class, 'updateClass']);
    Route::delete('classes/{schoolClass}', [AcademicApiController::class, 'destroyClass']);
    Route::put('classes/{schoolClass}/homeroom', [AcademicApiController::class, 'assignHomeroom']);
    Route::get('classes/{schoolClass}/students/export', [AcademicApiController::class, 'exportClassStudents']);

    Route::get('enrollments', [AcademicApiController::class, 'enrollments']);
    Route::post('enrollments', [AcademicApiController::class, 'storeEnrollment']);

    Route::get('teaching-assignments', [AcademicApiController::class, 'teachingAssignments']);
    Route::post('teaching-assignments', [AcademicApiController::class, 'storeTeachingAssignment']);
    Route::put('teaching-assignments/{teachingAssignment}', [AcademicApiController::class, 'updateTeachingAssignment']);
    Route::delete('teaching-assignments/{teachingAssignment}', [AcademicApiController::class, 'destroyTeachingAssignment']);
});
