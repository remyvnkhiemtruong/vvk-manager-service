<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ConductScore;
use App\Models\FeeInvoice;
use App\Models\ScoreEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->hasPermission('portal.view'), 403);

        $students = collect();
        $audiences = ['all'];

        if ($user->student) {
            $students = collect([$user->student]);
            $audiences[] = 'students';
        }

        if ($user->guardian) {
            $students = $user->guardian->students()->get();
            $audiences[] = 'guardians';
        }

        if ($user->staff) {
            $audiences[] = 'staff';
        }

        $studentIds = $students->pluck('id');

        return Inertia::render('Portal', [
            'students' => $students->map(fn ($student): array => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'scores' => ScoreEntry::query()
                    ->with(['subject:id,name', 'scoreColumn:id,code,name'])
                    ->where('student_id', $student->id)
                    ->latest()
                    ->limit(8)
                    ->get(['id', 'subject_id', 'score_column_id', 'score', 'comment', 'status', 'note'])
                    ->map(fn (ScoreEntry $score): array => [
                        'id' => $score->id,
                        'subject_name' => $score->subject?->name,
                        'score_column' => $score->scoreColumn?->code,
                        'score' => $score->score,
                        'comment' => $score->comment,
                        'status' => $score->status,
                    ]),
                'conduct' => ConductScore::query()
                    ->where('student_id', $student->id)
                    ->latest()
                    ->limit(4)
                    ->get(['score', 'rating', 'status', 'note']),
                'invoices' => FeeInvoice::query()
                    ->where('student_id', $student->id)
                    ->latest()
                    ->limit(6)
                    ->get(['invoice_no', 'total_amount', 'paid_amount', 'status', 'due_date']),
            ]),
            'announcements' => Announcement::query()
                ->where('status', 'published')
                ->whereIn('audience', $audiences)
                ->latest('published_at')
                ->limit(8)
                ->get(['title', 'body', 'audience', 'published_at']),
            'hasStudentContext' => $studentIds->isNotEmpty(),
        ]);
    }
}
