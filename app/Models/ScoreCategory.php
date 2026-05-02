<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }
}

