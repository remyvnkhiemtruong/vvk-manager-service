<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScoreCategory extends Model
{
    use SoftDeletes;

    protected $table = 'score_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'counts_toward_average' => 'boolean',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ScoreColumn::class, 'score_type_id');
    }
}
