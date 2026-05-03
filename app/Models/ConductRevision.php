<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductRevision extends Model
{
    protected $table = 'conduct_adjustments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ConductRecord::class, 'conduct_record_id');
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(ConductScore::class, 'conduct_score_summary_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
