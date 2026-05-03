<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignCriterion;
use App\Models\CampaignFile;
use App\Models\CampaignParticipant;
use App\Models\CampaignPointApplication;
use App\Models\CampaignResult;
use App\Models\CampaignResultScore;
use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\Student;
use App\Services\Conduct\ConductScoreService;
use App\Support\Audit\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignScoringService
{
    public function __construct(
        private readonly CampaignAccess $access,
        private readonly CampaignRankingService $rankings,
        private readonly ConductScoreService $conductScores
    ) {
    }

    public function criteria(Request $request, Campaign $campaign): array
    {
        $this->access->assertCanViewCampaign($request->user(), $campaign);

        return $campaign->criteria()
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->map(fn (CampaignCriterion $criterion): array => $this->criterionPayload($criterion))
            ->values()
            ->all();
    }

    public function saveCriteria(Request $request, Campaign $campaign, array $rows): array
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_criteria.update'), 403);
        $this->access->assertCanManage($request->user());

        DB::transaction(function () use ($request, $campaign, $rows): void {
            $keptIds = [];

            foreach (array_values($rows) as $index => $row) {
                $payload = [
                    'campaign_id' => $campaign->id,
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'max_score' => (float) ($row['max_score'] ?? 10),
                    'weight' => (float) ($row['weight'] ?? 1),
                    'order_index' => (int) ($row['order_index'] ?? ($index + 1)),
                    'status' => $row['status'] ?? 'active',
                ];

                $criterion = empty($row['id'])
                    ? CampaignCriterion::create($payload)
                    : tap(CampaignCriterion::where('campaign_id', $campaign->id)->findOrFail((int) $row['id']))->update($payload);

                $keptIds[] = $criterion->id;
            }

            CampaignCriterion::query()
                ->where('campaign_id', $campaign->id)
                ->whereNotIn('id', $keptIds)
                ->delete();

            Auditor::record('campaign_criteria.updated', $campaign, null, ['criteria_ids' => $keptIds], $request);
        });

        return $this->criteria($request, $campaign);
    }

    public function results(Request $request, Campaign $campaign): array
    {
        $this->access->assertCanViewCampaign($request->user(), $campaign);

        return CampaignResult::query()
            ->with(['participant.schoolClass:id,name', 'participant.student:id,student_code,full_name', 'scores.criterion', 'files'])
            ->where('campaign_id', $campaign->id)
            ->latest()
            ->get()
            ->map(fn (CampaignResult $result): array => $this->resultPayload($result))
            ->values()
            ->all();
    }

    public function saveResult(Request $request, Campaign $campaign, array $data, array $files = []): CampaignResult
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_results.update'), 403);
        $this->access->assertCanManage($request->user());

        $participant = CampaignParticipant::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'approved')
            ->findOrFail((int) $data['campaign_participant_id']);

        return DB::transaction(function () use ($request, $campaign, $participant, $data, $files): CampaignResult {
            $result = CampaignResult::query()->firstOrNew([
                'campaign_id' => $campaign->id,
                'campaign_participant_id' => $participant->id,
            ]);

            $before = $result->exists ? $this->resultSnapshot($result) : null;
            $status = $data['status'] ?? 'published';
            $result->fill([
                'award_title' => $data['award_title'] ?? null,
                'conduct_points' => array_key_exists('conduct_points', $data) ? (int) $data['conduct_points'] : null,
                'class_points' => array_key_exists('class_points', $data) ? (float) $data['class_points'] : null,
                'note' => $data['note'] ?? null,
                'status' => $status,
                'entered_by' => $request->user()?->id,
                'published_by' => in_array($status, ['published', 'final'], true) ? $request->user()?->id : null,
                'published_at' => in_array($status, ['published', 'final'], true) ? now() : null,
            ]);
            $result->save();

            $total = $this->syncScores($request, $campaign, $result, $data['scores'] ?? []);
            $result->forceFill(['total_score' => $total])->save();

            $this->storeEvidenceFiles($request, $result, $files);
            $this->rankings->recalculate($campaign);

            Auditor::record(
                $before ? 'campaign_results.updated' : 'campaign_results.created',
                $result,
                $before,
                $this->resultSnapshot($result->fresh()),
                $request
            );

            return $result->fresh(['participant.schoolClass', 'participant.student', 'participant.members.student', 'scores.criterion', 'files']);
        });
    }

    public function uploadEvidence(Request $request, CampaignResult $result, array $files): CampaignResult
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_results.update'), 403);
        $this->access->assertCanManage($request->user());

        $this->storeEvidenceFiles($request, $result, $files);

        return $result->fresh(['participant.schoolClass', 'participant.student', 'participant.members.student', 'scores.criterion', 'files']);
    }

    public function summarize(Request $request, Campaign $campaign, ?string $summaryReport = null): array
    {
        abort_unless($request->user()?->hasPermission('activities.campaign_results.update'), 403);
        $this->access->assertCanManage($request->user());

        $applied = ['conduct' => 0, 'class' => 0];

        DB::transaction(function () use ($request, $campaign, $summaryReport, &$applied): void {
            $before = Arr::only($campaign->getAttributes(), ['status', 'summary_report', 'summarized_by', 'summarized_at']);

            $results = CampaignResult::query()
                ->with(['participant.members', 'participant.schoolClass'])
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', ['published', 'final'])
                ->get();

            $rule = $this->campaignConductRule();

            foreach ($results as $result) {
                $participant = $result->participant;

                if (! $participant || $participant->status !== 'approved') {
                    continue;
                }

                $conductPoints = (int) ($result->conduct_points ?? $campaign->conduct_points_per_student ?? 0);
                if ($conductPoints > 0) {
                    foreach ($this->studentIdsForParticipant($participant, $campaign) as $studentId) {
                        if ($this->alreadyApplied($result, 'conduct', $studentId, null)) {
                            continue;
                        }

                        $classId = $this->access->classIdForStudent($studentId, (int) $campaign->semester_id) ?: (int) $participant->class_id;
                        $record = $this->createConductRecord($request, $campaign, $result, $rule, $studentId, $classId, $conductPoints);
                        CampaignPointApplication::create([
                            'campaign_id' => $campaign->id,
                            'campaign_result_id' => $result->id,
                            'campaign_participant_id' => $participant->id,
                            'application_type' => 'conduct',
                            'student_id' => $studentId,
                            'class_id' => $classId ?: null,
                            'conduct_record_id' => $record->id,
                            'points' => $conductPoints,
                            'applied_by' => $request->user()?->id,
                            'applied_at' => now(),
                        ]);
                        $applied['conduct']++;
                    }
                }

                $classPoints = (float) ($result->class_points ?? $campaign->class_competition_points ?? 0);
                $classId = (int) ($participant->class_id ?: $this->classIdFromParticipant($participant, $campaign));
                if ($classPoints > 0 && $classId > 0 && ! $this->alreadyApplied($result, 'class_competition', null, $classId)) {
                    $classScore = $this->applyClassScore($request, $campaign, $result, $classId, $classPoints);
                    CampaignPointApplication::create([
                        'campaign_id' => $campaign->id,
                        'campaign_result_id' => $result->id,
                        'campaign_participant_id' => $participant->id,
                        'application_type' => 'class_competition',
                        'class_id' => $classId,
                        'campaign_class_score_id' => $classScore->id,
                        'points' => $classPoints,
                        'applied_by' => $request->user()?->id,
                        'applied_at' => now(),
                    ]);
                    $applied['class']++;
                }
            }

            $campaign->forceFill([
                'status' => 'summarized',
                'summary_report' => $summaryReport ?? $campaign->summary_report,
                'summarized_by' => $request->user()?->id,
                'summarized_at' => now(),
            ])->save();

            Auditor::record('campaigns.summarized', $campaign, $before, Arr::only($campaign->fresh()->getAttributes(), ['status', 'summary_report', 'summarized_by', 'summarized_at']), $request, ['applied' => $applied]);
        });

        return $applied;
    }

    public function downloadEvidence(Request $request, CampaignResult $result, CampaignFile $file): StreamedResponse
    {
        $this->access->assertCanViewCampaign($request->user(), $result->campaign);
        abort_unless((int) $file->campaign_result_id === (int) $result->id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function resultPayload(CampaignResult $result): array
    {
        return [
            ...$this->rankings->resultPayload($result),
            'note' => $result->note,
            'scores' => $result->scores->map(fn (CampaignResultScore $score): array => [
                'id' => $score->id,
                'campaign_criterion_id' => $score->campaign_criterion_id,
                'criterion_name' => $score->criterion?->name,
                'score' => $score->score,
                'note' => $score->note,
            ])->values(),
            'files' => $result->files->map(fn (CampaignFile $file): array => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ])->values(),
        ];
    }

    private function syncScores(Request $request, Campaign $campaign, CampaignResult $result, array $scores): float
    {
        $criteria = $campaign->criteria()->where('status', 'active')->get()->keyBy('id');
        $total = 0.0;
        $keptIds = [];

        foreach ($scores as $row) {
            $criterionId = (int) ($row['campaign_criterion_id'] ?? $row['criterion_id'] ?? 0);
            /** @var CampaignCriterion|null $criterion */
            $criterion = $criteria->get($criterionId);

            if (! $criterion) {
                continue;
            }

            $scoreValue = (float) ($row['score'] ?? 0);
            if ($scoreValue < 0 || $scoreValue > (float) $criterion->max_score) {
                throw ValidationException::withMessages(['scores' => 'Điểm tiêu chí phải nằm trong thang điểm đã cấu hình.']);
            }

            $score = CampaignResultScore::query()->updateOrCreate(
                ['campaign_result_id' => $result->id, 'campaign_criterion_id' => $criterion->id],
                [
                    'score' => $scoreValue,
                    'note' => $row['note'] ?? null,
                    'scored_by' => $request->user()?->id,
                ]
            );
            $keptIds[] = $score->id;
            $total += $scoreValue * (float) $criterion->weight;
        }

        CampaignResultScore::query()
            ->where('campaign_result_id', $result->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        return round($total, 2);
    }

    private function storeEvidenceFiles(Request $request, CampaignResult $result, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('campaigns/'.$result->campaign_id.'/evidences/'.$result->id, 'local');
            CampaignFile::create([
                'campaign_id' => $result->campaign_id,
                'campaign_result_id' => $result->id,
                'file_type' => 'evidence',
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => $request->user()?->id,
            ]);
        }
    }

    private function studentIdsForParticipant(CampaignParticipant $participant, Campaign $campaign): Collection
    {
        if ($participant->participant_type === 'class' && $participant->class_id) {
            return DB::table('student_class_enrollments')
                ->where('class_id', $participant->class_id)
                ->where('semester_id', $campaign->semester_id)
                ->where('status', 'active')
                ->pluck('student_id')
                ->unique()
                ->values();
        }

        if ($participant->participant_type === 'team') {
            return $participant->members()->where('status', 'active')->pluck('student_id')->unique()->values();
        }

        return collect([(int) $participant->student_id])->filter()->values();
    }

    private function createConductRecord(Request $request, Campaign $campaign, CampaignResult $result, ConductRule $rule, int $studentId, int $classId, int $points): ConductRecord
    {
        $record = ConductRecord::create([
            'school_year_id' => $campaign->school_year_id,
            'semester_id' => $campaign->semester_id,
            'class_id' => $classId ?: null,
            'student_id' => $studentId,
            'conduct_rule_id' => $rule->id,
            'points' => $points,
            'recorded_date' => $campaign->end_date?->toDateString() ?? now()->toDateString(),
            'description' => 'Tự động cộng điểm từ phong trào: '.$campaign->title,
            'note' => $result->award_title,
            'status' => 'approved',
            'recorded_by' => $request->user()?->id,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'metadata' => [
                'source' => 'campaign_summary',
                'campaign_id' => $campaign->id,
                'campaign_result_id' => $result->id,
            ],
        ]);

        $summary = $this->conductScores->ensureSummary((int) $campaign->school_year_id, (int) $campaign->semester_id, $classId, $studentId);
        $this->conductScores->recalculate($summary);
        Auditor::record('conduct_records.created_from_campaign', $record, null, $record->fresh()->getAttributes(), $request);

        return $record;
    }

    private function applyClassScore(Request $request, Campaign $campaign, CampaignResult $result, int $classId, float $points): object
    {
        $classScore = DB::table('campaign_class_scores')
            ->where('campaign_id', $campaign->id)
            ->whereNull('campaign_criteria_id')
            ->where('class_id', $classId)
            ->first();

        if ($classScore) {
            DB::table('campaign_class_scores')
                ->where('id', $classScore->id)
                ->update([
                    'score' => (float) $classScore->score + $points,
                    'note' => trim(($classScore->note ? $classScore->note."\n" : '').'Cộng từ kết quả #'.$result->id),
                    'campaign_result_id' => $result->id,
                    'applied_by' => $request->user()?->id,
                    'applied_at' => now(),
                    'updated_at' => now(),
                ]);

            return DB::table('campaign_class_scores')->where('id', $classScore->id)->first();
        }

        $id = DB::table('campaign_class_scores')->insertGetId([
            'campaign_id' => $campaign->id,
            'campaign_result_id' => $result->id,
            'campaign_criteria_id' => null,
            'class_id' => $classId,
            'score' => $points,
            'note' => 'Cộng từ kết quả #'.$result->id,
            'applied_by' => $request->user()?->id,
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('campaign_class_scores')->where('id', $id)->first();
    }

    private function alreadyApplied(CampaignResult $result, string $type, ?int $studentId, ?int $classId): bool
    {
        return CampaignPointApplication::query()
            ->where('campaign_result_id', $result->id)
            ->where('application_type', $type)
            ->when($studentId, fn (Builder $query): Builder => $query->where('student_id', $studentId))
            ->when($classId, fn (Builder $query): Builder => $query->where('class_id', $classId))
            ->exists();
    }

    private function campaignConductRule(): ConductRule
    {
        return ConductRule::query()->firstOrCreate(
            ['code' => 'MOVEMENT_PARTICIPATION'],
            [
                'name' => 'Tham gia phong trào',
                'points' => 5,
                'rule_type' => 'bonus',
                'severity' => 'normal',
                'requires_approval' => false,
                'description' => 'Tự động cộng từ module phong trào',
                'sort_order' => 99,
                'status' => 'active',
            ]
        );
    }

    private function classIdFromParticipant(CampaignParticipant $participant, Campaign $campaign): ?int
    {
        $studentId = $participant->student_id ?: $participant->members()->value('student_id');

        return $studentId ? $this->access->classIdForStudent((int) $studentId, (int) $campaign->semester_id) : null;
    }

    private function criterionPayload(CampaignCriterion $criterion): array
    {
        return [
            'id' => $criterion->id,
            'campaign_id' => $criterion->campaign_id,
            'code' => $criterion->code,
            'name' => $criterion->name,
            'description' => $criterion->description,
            'max_score' => $criterion->max_score,
            'weight' => $criterion->weight,
            'order_index' => $criterion->order_index,
            'status' => $criterion->status,
        ];
    }

    private function resultSnapshot(CampaignResult $result): array
    {
        return Arr::only($result->fresh()?->getAttributes() ?? $result->getAttributes(), [
            'id',
            'campaign_id',
            'campaign_participant_id',
            'total_score',
            'rank',
            'award_title',
            'conduct_points',
            'class_points',
            'status',
            'entered_by',
            'published_by',
            'published_at',
        ]);
    }
}
