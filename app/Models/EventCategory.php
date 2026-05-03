<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allowed_grade_ids' => 'array',
            'allowed_class_ids' => 'array',
            'drop_extreme_scores' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class, 'event_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(EventCategoryCriterion::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(EventTeam::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }
}
