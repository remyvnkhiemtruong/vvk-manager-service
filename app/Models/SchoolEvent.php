<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolEvent extends Model
{
    protected $table = 'events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_modes' => 'array',
            'metadata' => 'array',
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

    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class, 'event_id');
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(EventOrganizer::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(EventTeam::class, 'event_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class, 'event_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'event_id');
    }

    public function judges(): HasMany
    {
        return $this->hasMany(EventJudge::class, 'event_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EventResult::class, 'event_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(EventAward::class, 'event_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EventFile::class, 'event_id');
    }
}
