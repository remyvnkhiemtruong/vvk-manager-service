<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $table = 'fee_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2'];
    }
}
