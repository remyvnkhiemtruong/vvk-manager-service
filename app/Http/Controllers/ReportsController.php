<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\Assessment\ScoreReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(private readonly ScoreReportService $service)
    {
    }

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = $request->only(['school_year_id', 'semester_id', 'class_id', 'subject_id', 'threshold', 'from_semester_id', 'to_semester_id']);

        return Inertia::render('Reports/Index', [
            'filters' => $filters,
            'options' => [
                'schoolYears' => SchoolYear::query()->select('id', 'name')->orderByDesc('id')->get(),
                'semesters' => Semester::query()->select('id', 'name')->orderBy('term_number')->get(),
                'classes' => SchoolClass::query()->select('id', 'name')->orderBy('name')->get(),
                'subjects' => Subject::query()->select('id', 'name')->orderBy('name')->get(),
            ],
            'overview' => $this->service->buildOverview($request->user(), $filters),
            'lowScoreStudents' => $this->service->lowScoreStudents($request->user(), $filters),
            'improvedStudents' => $this->service->improvedStudents($request->user(), $filters),
        ]);
    }
}
