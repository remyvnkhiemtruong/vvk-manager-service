<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CampaignRankingService
{
    public function __construct(private readonly CampaignAccess $access)
    {
    }

    public function rankings(Request $request, Campaign $campaign): array
    {
        $this->access->assertCanViewCampaign($request->user(), $campaign);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'status' => $campaign->status,
            ],
            'rows' => $this->rankingRows($campaign),
        ];
    }

    public function recalculate(Campaign $campaign): void
    {
        $results = CampaignResult::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['published', 'final'])
            ->orderByDesc('total_score')
            ->orderBy('id')
            ->get();

        $rank = 0;
        $position = 0;
        $previousScore = null;

        foreach ($results as $result) {
            $position++;
            $score = (string) $result->total_score;

            if ($previousScore === null || $score !== $previousScore) {
                $rank = $position;
                $previousScore = $score;
            }

            $result->forceFill(['rank' => $rank])->save();
        }
    }

    public function rankingRows(Campaign $campaign): array
    {
        return CampaignResult::query()
            ->with(['participant.schoolClass:id,name', 'participant.student:id,student_code,full_name', 'participant.members.student:id,student_code,full_name'])
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['published', 'final'])
            ->orderByRaw('rank is null')
            ->orderBy('rank')
            ->orderByDesc('total_score')
            ->get()
            ->map(fn (CampaignResult $result): array => $this->resultPayload($result))
            ->values()
            ->all();
    }

    public function resultPayload(CampaignResult $result): array
    {
        $participant = $result->participant;

        return [
            'id' => $result->id,
            'campaign_id' => $result->campaign_id,
            'campaign_participant_id' => $result->campaign_participant_id,
            'participant_type' => $participant?->participant_type,
            'participant_name' => $participant?->participant_name ?: $participant?->student?->full_name ?: $participant?->schoolClass?->name,
            'class_name' => $participant?->schoolClass?->name,
            'student_code' => $participant?->student?->student_code,
            'student_name' => $participant?->student?->full_name,
            'member_count' => $participant?->members?->count() ?? 0,
            'total_score' => $result->total_score,
            'rank' => $result->rank,
            'award_title' => $result->award_title,
            'conduct_points' => $result->conduct_points,
            'class_points' => $result->class_points,
            'status' => $result->status,
            'published_at' => $result->published_at?->toDateTimeString(),
        ];
    }
}
