<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPointApplication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(CampaignResult::class, 'campaign_result_id');
    }
}
