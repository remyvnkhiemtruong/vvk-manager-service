<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreRevision extends Model
{
    protected $table = 'score_change_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}
