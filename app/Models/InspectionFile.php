<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionFile extends Model
{
    protected $fillable = [
        'inspection_id', 'uploaded_by', 'source_inspection_id',
        'original_name', 'path', 'size', 'mime',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'source_inspection_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Файл подтянут снизу при приёмке подпроверки. */
    public function isAggregated(): bool
    {
        return $this->source_inspection_id !== null;
    }
}
