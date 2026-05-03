<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConductEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'conduct_record_evidences';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ConductRecord::class, 'conduct_record_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
