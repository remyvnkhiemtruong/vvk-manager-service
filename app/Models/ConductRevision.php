<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConductRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}

