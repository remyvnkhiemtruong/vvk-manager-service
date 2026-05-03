<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventJudgeScore extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function result(): BelongsTo
    {
        return $this->belongsTo(EventResult::class, 'event_result_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EventCategoryCriterion::class, 'event_category_criterion_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(EventJudge::class, 'event_judge_id');
    }
}
