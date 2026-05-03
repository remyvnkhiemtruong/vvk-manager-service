<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CampaignParticipant;
use App\Models\ConductScore;
use App\Models\EventRegistration;
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
                'campaigns' => CampaignParticipant::query()
                    ->with(['campaign:id,title,campaign_type,start_date,end_date,status', 'result:id,campaign_participant_id,total_score,rank,award_title,status'])
                    ->where(function ($query) use ($student): void {
                        $query->where('student_id', $student->id)
                            ->orWhereHas('members', fn ($member) => $member->where('student_id', $student->id));
                    })
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (CampaignParticipant $participant): array => [
                        'id' => $participant->id,
                        'title' => $participant->campaign?->title,
                        'type' => config('school.campaigns.types.'.$participant->campaign?->campaign_type, $participant->campaign?->campaign_type),
                        'status' => config('school.campaigns.registration_statuses.'.$participant->status, $participant->status),
                        'campaign_status' => config('school.campaigns.statuses.'.$participant->campaign?->status, $participant->campaign?->status),
                        'rank' => $participant->result?->rank,
                        'award_title' => $participant->result?->award_title,
                    ]),
                'events' => EventRegistration::query()
                    ->with(['event:id,title,event_type,starts_at,ends_at,status', 'category:id,name', 'team.members', 'student:id,student_code,full_name'])
                    ->where(function ($query) use ($student): void {
                        $query->where('student_id', $student->id)
                            ->orWhereHas('team.members', fn ($member) => $member->where('student_id', $student->id));
                    })
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (EventRegistration $registration): array => [
                        'id' => $registration->id,
                        'title' => $registration->event?->title,
                        'category' => $registration->category?->name,
                        'type' => config('school.events.types.'.$registration->event?->event_type, $registration->event?->event_type),
                        'status' => config('school.events.registration_statuses.'.$registration->status, $registration->status),
                        'event_status' => config('school.events.statuses.'.$registration->event?->status, $registration->event?->status),
                        'team_name' => $registration->team?->name,
                    ]),
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
