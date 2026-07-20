<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSetting extends Model
{
    protected $fillable = [
        'manager_scope', 'allow_subassignments', 'sub_scope', 'max_depth',
        'aggregate_up', 'coexecutors_enabled', 'controller_enabled',
        'deadline_extension', 'document_type_id', 'blank_template_id',
    ];

    protected $casts = [
        'allow_subassignments' => 'boolean',
        'aggregate_up'         => 'boolean',
        'coexecutors_enabled'  => 'boolean',
        'controller_enabled'   => 'boolean',
    ];

    public const SCOPES = [
        'subordinates' => 'Только подчинённые',
        'direction'    => 'Своё направление',
        'organization' => 'Вся организация',
    ];

    public const DEADLINE_MODES = [
        'disabled' => 'Запрещено',
        'free'     => 'Разрешено сразу',
        'approval' => 'С одобрением постановщика',
    ];

    private static ?AssignmentSetting $cached = null;

    /** Единственная строка настроек (создаётся при первом обращении). */
    public static function current(): self
    {
        return self::$cached ??= self::first() ?? self::create([]);
    }

    public function blank(): BelongsTo
    {
        return $this->belongsTo(BlankTemplate::class, 'blank_template_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}
