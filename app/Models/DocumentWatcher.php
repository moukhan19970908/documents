<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Правило наблюдения: watcher видит документы target'а (в рамках scope),
 * но не может выполнять по ним действия.
 */
class DocumentWatcher extends Model
{
    protected $fillable = ['watcher_id', 'target_id', 'scope'];

    /** Человекочитаемые области наблюдения. */
    public const SCOPES = [
        'participant' => 'Все документы, где участвует',
        'initiator'   => 'Только документы, где он инициатор',
        'approver'    => 'Только документы, где он согласующий',
    ];

    public function watcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'watcher_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
