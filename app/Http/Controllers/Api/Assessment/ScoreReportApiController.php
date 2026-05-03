<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Services\Assessment\ScoreReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoreReportApiController extends Controller
{
    public function __construct(private readonly ScoreReportService $service)
    {
    }

    public function lowScores(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return response()->json($this->service->lowScoreStudents($request->user(), $request->all()));
    }

    public function improved(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return response()->json($this->service->improvedStudents($request->user(), $request->all()));
    }
}
