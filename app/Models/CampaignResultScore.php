<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignResultScore extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(CampaignResult::class, 'campaign_result_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(CampaignCriterion::class, 'campaign_criterion_id');
    }
}
