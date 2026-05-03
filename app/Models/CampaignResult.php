<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignResult extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'rank' => 'integer',
            'conduct_points' => 'integer',
            'class_points' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipant::class, 'campaign_participant_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CampaignResultScore::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CampaignFile::class);
    }
}
