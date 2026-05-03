<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScoreColumn extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'locked_at' => 'datetime',
            'unlock_requested_at' => 'datetime',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scoreType(): BelongsTo
    {
        return $this->belongsTo(ScoreCategory::class, 'score_type_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ScoreEntry::class);
    }

    public function lockRequests(): HasMany
    {
        return $this->hasMany(ScoreLockRequest::class);
    }
}
