<?php

use App\Http\Controllers\Api\Assessment\ScoreReportApiController;
use App\Http\Controllers\Api\Assessment\AssessmentApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Academic\AcademicApiController;
use App\Http\Controllers\Api\Conduct\ConductApiController;
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

Route::middleware('jwt.auth')->prefix('reports')->group(function (): void {
    Route::get('scores/low', [ScoreReportApiController::class, 'lowScores']);
    Route::get('scores/improved', [ScoreReportApiController::class, 'improved']);
});

Route::middleware('jwt.auth')->prefix('assessment')->group(function (): void {
    Route::get('scorebooks', [AssessmentApiController::class, 'scorebooks']);
    Route::get('scorebooks/{class}/{subject}/{semester}', [AssessmentApiController::class, 'scorebook']);
    Route::post('score-columns', [AssessmentApiController::class, 'storeColumn']);
    Route::put('score-columns/{column}', [AssessmentApiController::class, 'updateColumn']);
    Route::delete('score-columns/{column}', [AssessmentApiController::class, 'deleteColumn']);
    Route::put('scores/bulk', [AssessmentApiController::class, 'saveScores']);
    Route::post('score-columns/{column}/lock', [AssessmentApiController::class, 'lockColumn']);
    Route::post('score-columns/{column}/request-unlock', [AssessmentApiController::class, 'requestUnlock']);
    Route::post('score-columns/{column}/approve-unlock', [AssessmentApiController::class, 'approveUnlock']);
    Route::post('score-columns/{column}/reject-unlock', [AssessmentApiController::class, 'rejectUnlock']);
    Route::post('scores/import', [AssessmentApiController::class, 'importScores']);
    Route::get('classes/{class}/scores/export', [AssessmentApiController::class, 'exportScores']);
    Route::get('students/{student}/scores', [AssessmentApiController::class, 'studentScores']);
    Route::get('score-revisions', [AssessmentApiController::class, 'revisions']);
    Route::get('reports', [AssessmentApiController::class, 'reports']);
});

Route::middleware('jwt.auth')->prefix('conduct')->group(function (): void {
    Route::get('rules', [ConductApiController::class, 'rules']);
    Route::post('rules', [ConductApiController::class, 'storeRule']);
    Route::put('rules/{rule}', [ConductApiController::class, 'updateRule']);
    Route::delete('rules/{rule}', [ConductApiController::class, 'deleteRule']);
    Route::get('records', [ConductApiController::class, 'records']);
    Route::post('records', [ConductApiController::class, 'storeRecord']);
    Route::put('records/{record}', [ConductApiController::class, 'updateRecord']);
    Route::post('records/{record}/approve', [ConductApiController::class, 'approve']);
    Route::post('records/{record}/reject', [ConductApiController::class, 'reject']);
    Route::post('records/{record}/cancel', [ConductApiController::class, 'cancel']);
    Route::get('summaries', [ConductApiController::class, 'summaries']);
    Route::get('classes/{class}/summaries', [ConductApiController::class, 'classSummaries']);
    Route::post('summaries/recompute', [ConductApiController::class, 'recompute']);
    Route::put('summaries/{summary}/adjust', [ConductApiController::class, 'adjust']);
    Route::put('summaries/{summary}/comment', [ConductApiController::class, 'comment']);
    Route::post('summaries/{summary}/lock', [ConductApiController::class, 'lock']);
    Route::post('summaries/{summary}/unlock', [ConductApiController::class, 'unlock']);
    Route::get('students/{student}/timeline', [ConductApiController::class, 'timeline']);
    Route::get('records/{record}/evidences/{evidence}', [ConductApiController::class, 'evidence']);
    Route::get('reports', [ConductApiController::class, 'reports']);
});
