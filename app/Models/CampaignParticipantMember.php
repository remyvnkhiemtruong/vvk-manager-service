<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignParticipantMember extends Model
{
    protected $guarded = [];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipant::class, 'campaign_participant_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
