<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatchSet extends Model
{
    protected $guarded = [];

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'event_match_id');
    }
}
