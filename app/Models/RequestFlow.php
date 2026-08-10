<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Граф-процесс заявки (отпуск/командировка) на бесшовном стеке. */
class RequestFlow extends Model
{
    protected $fillable = ['request_type', 'name', 'status', 'version'];

    /** Виды заявок, у которых есть граф-конструктор. */
    public const REQUEST_TYPES = [
        'vacation' => 'Отпуск',
        'trip'     => 'Командировка',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(RequestFlowNode::class, 'flow_id');
    }

    /** Черновик графа для вида заявки — создаётся при первом открытии конструктора. */
    public static function forType(string $requestType): self
    {
        return static::firstOrCreate(
            ['request_type' => $requestType],
            ['name' => self::REQUEST_TYPES[$requestType] ?? $requestType, 'status' => 'draft'],
        );
    }
}
