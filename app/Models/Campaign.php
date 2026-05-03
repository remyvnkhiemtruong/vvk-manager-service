<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_modes' => 'array',
            'conduct_points_per_student' => 'integer',
            'class_competition_points' => 'decimal:2',
            'summarized_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function summarizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'summarized_by');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(CampaignCriterion::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CampaignParticipant::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CampaignResult::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CampaignFile::class);
    }
}
