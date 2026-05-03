<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConductRule extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(ConductRecord::class);
    }
}
