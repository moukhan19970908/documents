<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureFile extends Model
{
    protected $fillable = [
        'procedure_id', 'procedure_stage_run_id', 'uploaded_by',
        'original_name', 'path', 'size', 'mime',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProcedureStageRun::class, 'procedure_stage_run_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
