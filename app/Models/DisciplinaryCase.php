<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryCase extends Model
{
    protected $table = 'discipline_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['incident_date' => 'date'];
    }
}
