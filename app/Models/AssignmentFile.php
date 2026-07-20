<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentFile extends Model
{
    protected $fillable = [
        'assignment_id', 'uploaded_by', 'source_assignment_id',
        'original_name', 'path', 'size', 'mime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** Узел-источник, если файл подтянут снизу при приёмке. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'source_assignment_id');
    }

    public function isAggregated(): bool
    {
        return $this->source_assignment_id !== null;
    }
}
