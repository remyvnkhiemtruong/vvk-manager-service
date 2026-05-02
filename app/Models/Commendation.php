<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commendation extends Model
{
    protected $table = 'rewards';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_date' => 'date'];
    }
}
