<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelatedFile extends Model
{
    protected $fillable = [
        'document_id', 'uploaded_by', 'file_path',
        'file_name', 'file_size', 'mime_type', 'description',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' КБ';
        }
        return $bytes . ' Б';
    }
}
