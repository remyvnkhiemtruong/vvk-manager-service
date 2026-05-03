<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventMatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['played_at' => 'datetime', 'metadata' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class, 'event_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EventSchedule::class, 'event_schedule_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'winner_team_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(EventMatchSet::class);
    }
}
