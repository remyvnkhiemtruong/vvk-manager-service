<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignCriterion;
use App\Models\CampaignFile;
use App\Models\CampaignParticipant;
use App\Models\CampaignResult;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Support\Audit\Auditor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignService
{
    public function __construct(private readonly CampaignAccess $access)
    {
    }

    public function campaigns(Request $request, array $filters = []): LengthAwarePaginator
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.view'), 403);

        return $this->access->scopeCampaigns($request->user(), Campaign::query())
            ->with(['schoolYear:id,name', 'semester:id,name', 'creator:id,name'])
            ->withCount(['participants', 'results'])
            ->when(! empty($filters['school_year_id']), fn (Builder $query): Builder => $query->where('school_year_id', (int) $filters['school_year_id']))
            ->when(! empty($filters['semester_id']), fn (Builder $query): Builder => $query->where('semester_id', (int) $filters['semester_id']))
            ->when(! empty($filters['campaign_type']), fn (Builder $query): Builder => $query->where('campaign_type', $filters['campaign_type']))
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): Builder {
                $search = '%'.trim((string) $filters['search']).'%';

                return $query->where(fn (Builder $builder): Builder => $builder
                    ->where('title', 'like', $search)
                    ->orWhere('organizer_unit', 'like', $search)
                    ->orWhere('description', 'like', $search));
            })
            ->latest('start_date')
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100))
            ->withQueryString()
            ->through(fn (Campaign $campaign): array => $this->campaignPayload($campaign));
    }

    public function findForView(Request $request, Campaign $campaign): Campaign
    {
        $this->access->assertCanViewCampaign($request->user(), $campaign);

        return $campaign->load([
            'schoolYear:id,name',
            'semester:id,name',
            'criteria' => fn ($query) => $query->orderBy('order_index')->orderBy('id'),
            'files' => fn ($query) => $query->where('file_type', 'plan')->latest(),
        ]);
    }

    public function create(Request $request, array $data, ?UploadedFile $planFile = null): Campaign
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.create'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $data, $planFile): Campaign {
            $payload = $this->campaignData($data);
            $payload['created_by'] = $request->user()?->id;

            $campaign = Campaign::create($payload);
            $this->ensureDefaultCriteria($campaign);

            if ($planFile) {
                $this->storeFile($request, $campaign, $planFile, 'plan');
            }

            Auditor::record('campaigns.created', $campaign, null, $this->snapshot($campaign->fresh()), $request);

            return $campaign->fresh(['criteria', 'files']);
        });
    }

    public function update(Request $request, Campaign $campaign, array $data, ?UploadedFile $planFile = null): Campaign
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.update'), 403);
        $this->access->assertCanManage($request->user());

        return DB::transaction(function () use ($request, $campaign, $data, $planFile): Campaign {
            $before = $this->snapshot($campaign);
            $campaign->fill($this->campaignData($data))->save();

            if ($planFile) {
                $this->storeFile($request, $campaign, $planFile, 'plan');
            }

            Auditor::record('campaigns.updated', $campaign, $before, $this->snapshot($campaign->fresh()), $request);

            return $campaign->fresh(['criteria', 'files']);
        });
    }

    public function delete(Request $request, Campaign $campaign): void
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.delete'), 403);
        $this->access->assertCanManage($request->user());

        DB::transaction(function () use ($request, $campaign): void {
            $before = $this->snapshot($campaign);
            $campaign->delete();
            Auditor::record('campaigns.deleted', $campaign, $before, null, $request);
        });
    }

    public function dashboard(Request $request): array
    {
        abort_unless($request->user()?->hasPermission('activities.campaigns.view'), 403);

        $campaignQuery = $this->access->scopeCampaigns($request->user(), Campaign::query());
        $participantQuery = $this->access->scopeParticipants($request->user(), CampaignParticipant::query());

        return [
            'stats' => [
                'total' => (clone $campaignQuery)->count(),
                'open' => (clone $campaignQuery)->where('status', 'registration_open')->count(),
                'running' => (clone $campaignQuery)->where('status', 'in_progress')->count(),
                'summarized' => (clone $campaignQuery)->where('status', 'summarized')->count(),
                'pending_registrations' => (clone $participantQuery)->where('status', 'pending')->count(),
            ],
            'upcoming' => $campaignQuery
                ->whereIn('status', ['registration_open', 'in_progress'])
                ->orderBy('start_date')
                ->limit(8)
                ->get()
                ->map(fn (Campaign $campaign): array => $this->campaignPayload($campaign))
                ->values(),
            'recentResults' => CampaignResult::query()
                ->with(['campaign:id,title', 'participant:id,participant_name,student_id,class_id'])
                ->whereHas('campaign', fn (Builder $query): Builder => $this->access->scopeCampaigns($request->user(), $query))
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (CampaignResult $result): array => [
                    'id' => $result->id,
                    'campaign_title' => $result->campaign?->title,
                    'participant_name' => $result->participant?->participant_name,
                    'total_score' => $result->total_score,
                    'rank' => $result->rank,
                    'award_title' => $result->award_title,
                ])
                ->values(),
        ];
    }

    public function lookups(Request $request): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']),
            'semesters' => Semester::query()->orderByDesc('school_year_id')->orderBy('term_number')->get(['id', 'school_year_id', 'name', 'term_number', 'is_active']),
            'classes' => $this->access->classQueryFor($request->user())->orderBy('name')->get(['id', 'school_year_id', 'grade_id', 'name']),
            'students' => $this->access->studentQueryFor($request->user())->orderBy('full_name')->get(['id', 'student_code', 'full_name']),
            'types' => config('school.campaigns.types', []),
            'statuses' => config('school.campaigns.statuses', []),
            'targetAudiences' => config('school.campaigns.target_audiences', []),
            'registrationModes' => config('school.campaigns.registration_modes', []),
        ];
    }

    public function storeFile(Request $request, Campaign $campaign, UploadedFile $file, string $type): CampaignFile
    {
        $this->access->assertCanManage($request->user());

        $path = $file->store('campaigns/'.$campaign->id.'/'.$type, 'local');
        $record = CampaignFile::create([
            'campaign_id' => $campaign->id,
            'file_type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()?->id,
        ]);

        Auditor::record('campaign_files.created', $record, null, $record->fresh()->getAttributes(), $request);

        return $record;
    }

    public function downloadFile(Request $request, Campaign $campaign, CampaignFile $file): StreamedResponse
    {
        $this->access->assertCanViewCampaign($request->user(), $campaign);
        abort_unless((int) $file->campaign_id === (int) $campaign->id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function campaignPayload(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'school_year_id' => $campaign->school_year_id,
            'school_year_name' => $campaign->schoolYear?->name,
            'semester_id' => $campaign->semester_id,
            'semester_name' => $campaign->semester?->name,
            'title' => $campaign->title,
            'campaign_type' => $campaign->campaign_type,
            'type_label' => config('school.campaigns.types.'.$campaign->campaign_type, $campaign->campaign_type),
            'organizer_unit' => $campaign->organizer_unit,
            'target_audience' => $campaign->target_audience,
            'registration_modes' => $campaign->registration_modes ?? ['individual', 'team', 'class'],
            'start_date' => $campaign->start_date?->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'description' => $campaign->description,
            'summary_report' => $campaign->summary_report,
            'conduct_points_per_student' => $campaign->conduct_points_per_student,
            'class_competition_points' => $campaign->class_competition_points,
            'status' => $campaign->status,
            'status_label' => config('school.campaigns.statuses.'.$campaign->status, $campaign->status),
            'participants_count' => $campaign->participants_count ?? null,
            'results_count' => $campaign->results_count ?? null,
            'created_by' => $campaign->creator?->name,
            'summarized_at' => $campaign->summarized_at?->toDateTimeString(),
        ];
    }

    private function campaignData(array $data): array
    {
        $modes = array_values(array_filter((array) ($data['registration_modes'] ?? ['individual', 'team', 'class'])));

        return [
            ...Arr::only($data, [
                'school_year_id',
                'semester_id',
                'title',
                'campaign_type',
                'organizer_unit',
                'target_audience',
                'start_date',
                'end_date',
                'description',
                'summary_report',
                'status',
            ]),
            'registration_modes' => $modes ?: ['individual', 'team', 'class'],
            'conduct_points_per_student' => (int) ($data['conduct_points_per_student'] ?? 0),
            'class_competition_points' => (float) ($data['class_competition_points'] ?? 0),
        ];
    }

    private function ensureDefaultCriteria(Campaign $campaign): void
    {
        if ($campaign->criteria()->exists()) {
            return;
        }

        foreach (config('school.campaigns.default_criteria', []) as $index => $criterion) {
            CampaignCriterion::create([
                'campaign_id' => $campaign->id,
                'code' => $criterion['code'],
                'name' => $criterion['name'],
                'description' => $criterion['description'] ?? null,
                'max_score' => $criterion['max_score'] ?? 10,
                'weight' => $criterion['weight'] ?? 1,
                'order_index' => $index + 1,
                'status' => 'active',
            ]);
        }
    }

    private function snapshot(Campaign $campaign): array
    {
        return Arr::only($campaign->fresh()?->getAttributes() ?? $campaign->getAttributes(), [
            'id',
            'school_year_id',
            'semester_id',
            'title',
            'campaign_type',
            'organizer_unit',
            'target_audience',
            'registration_modes',
            'start_date',
            'end_date',
            'status',
            'conduct_points_per_student',
            'class_competition_points',
            'summary_report',
            'summarized_by',
            'summarized_at',
        ]);
    }
}
