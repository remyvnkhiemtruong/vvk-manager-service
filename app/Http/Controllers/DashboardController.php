<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ConductScore;
use App\Models\FeeInvoice;
use App\Models\SchoolEvent;
use App\Models\ScoreEntry;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), 403);

        if (($request->user()->hasRole('phu_huynh') || $request->user()->hasRole('hoc_sinh')) && $request->user()->hasPermission('portal.view')) {
            return redirect()->route('portal');
        }

        $stats = [
            ['label' => 'Học sinh', 'value' => Student::count(), 'tone' => 'blue'],
            ['label' => 'Giáo viên/Nhân sự', 'value' => Staff::count(), 'tone' => 'green'],
            ['label' => 'Điểm đã nhập', 'value' => ScoreEntry::count(), 'tone' => 'amber'],
            ['label' => 'Phiếu học phí', 'value' => FeeInvoice::count(), 'tone' => 'rose'],
        ];

        $activityStats = [
            'events' => SchoolEvent::count(),
            'conductScores' => ConductScore::count(),
            'unpaidInvoices' => FeeInvoice::where('status', '!=', 'paid')->count(),
        ];

        $recentAudits = $request->user()->hasPermission('audit.view')
            ? AuditLog::with('actor:id,name,email')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor?->name ?? 'System',
                    'subject' => class_basename((string) $log->subject_type).' #'.$log->subject_id,
                    'created_at' => $log->created_at?->format('d/m/Y H:i'),
                ])
            : collect();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'activityStats' => $activityStats,
            'recentAudits' => $recentAudits,
        ]);
    }
}
