<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAwardRecipient extends Model
{
    protected $guarded = [];

    public function award(): BelongsTo
    {
        return $this->belongsTo(EventAward::class, 'event_award_id');
    }
}
