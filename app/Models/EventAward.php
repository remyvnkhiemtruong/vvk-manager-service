<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAward extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['awarded_date' => 'date', 'metadata' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class, 'event_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(EventResult::class, 'event_result_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EventAwardRecipient::class);
    }
}
