<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureTaskFile extends Model
{
    protected $fillable = [
        'procedure_task_id', 'uploaded_by', 'original_name', 'path', 'size', 'mime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcedureTask::class, 'procedure_task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
