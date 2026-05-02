<?php

namespace App\Http\Controllers;

use App\Models\ConductScore;
use App\Models\FeeInvoice;
use App\Models\SchoolEvent;
use App\Models\ScoreEntry;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return Inertia::render('Reports/Index', [
            'summary' => [
                'students' => Student::count(),
                'averageScore' => round((float) ScoreEntry::avg('score'), 2),
                'averageConduct' => round((float) ConductScore::avg('score'), 1),
                'events' => SchoolEvent::count(),
                'unpaidAmount' => (float) FeeInvoice::query()->select(DB::raw('SUM(total_amount - paid_amount) as amount'))->value('amount'),
            ],
            'invoiceStatus' => FeeInvoice::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'eventTypes' => SchoolEvent::query()
                ->select('event_type', DB::raw('count(*) as total'))
                ->groupBy('event_type')
                ->pluck('total', 'event_type'),
        ]);
    }
}

