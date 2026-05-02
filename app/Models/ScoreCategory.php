<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreCategory extends Model
{
    protected $table = 'score_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }
}
