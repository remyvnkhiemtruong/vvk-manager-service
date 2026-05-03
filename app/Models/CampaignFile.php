<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignFile extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipant::class, 'campaign_participant_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(CampaignResult::class, 'campaign_result_id');
    }
}
