<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryAction extends Model
{
    protected $table = 'discipline_actions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['action_date' => 'date'];
    }
}
