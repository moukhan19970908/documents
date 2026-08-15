<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Неизменяемая архивная копия завершённого процесса (см. ТЗ 26).
 * Все «срезы» архива — запросы по денормализованным полям этой таблицы.
 */
class ArchivedDocument extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'title', 'number',
        'document_type_id', 'document_subtype_id', 'direction_id', 'department_id',
        'initiator_id', 'counterparty', 'metadata',
        'body_html', 'file_path', 'file_name', 'file_size', 'approval_sheet_path',
        'acknowledgment_sheet_path', 'acceptance_sheet_path',
        'content_hash', 'archived_at', 'archived_by',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'archived_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'direction_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' КБ';
        }
        return $bytes . ' Б';
    }
}
