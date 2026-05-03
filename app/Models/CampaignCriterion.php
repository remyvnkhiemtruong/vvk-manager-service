<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignCriterion extends Model
{
    use SoftDeletes;

    protected $table = 'campaign_criteria';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'order_index' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function resultScores(): HasMany
    {
        return $this->hasMany(CampaignResultScore::class, 'campaign_criterion_id');
    }
}
