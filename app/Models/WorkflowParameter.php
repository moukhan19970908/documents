<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question asked at document creation whose answer decides which stages join the route.
 * Not to be confused with DocumentField: that one describes the document, this one routes it.
 */
class WorkflowParameter extends Model
{
    protected $fillable = [
        'workflow_id', 'key', 'label', 'type', 'options',
        'is_required', 'default_value', 'sort_order',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
    ];

    public const TYPES = [
        'select'    => 'Список',
        'radio'     => 'Радио',
        'boolean'   => 'Да / Нет',
        'number'    => 'Число',
        'reference' => 'Справочник',
        'date'      => 'Дата',
    ];

    /** Types whose answers come from a fixed set of options. */
    public const TYPES_WITH_OPTIONS = ['select', 'radio'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
