<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Numerator extends Model
{
    protected $fillable = [
        'key', 'name', 'mask', 'scope', 'shared_counter', 'reset_period', 'padding',
        'start_value', 'assign_moment', 'allow_manual', 'manual_roles',
    ];

    protected $casts = [
        'scope'          => 'array',
        'manual_roles'   => 'array',
        'allow_manual'   => 'boolean',
        'shared_counter' => 'boolean',
    ];

    /** Глобальные потоки нумерации, настраиваемые на вкладке «Нумерация». */
    public const KEYS = [
        'document'         => 'Документ',
        'order'            => 'Приказ',
        'assignment'       => 'Поручение',
        'procedure'        => 'Процедура',
        'inspection'       => 'Проверка',
        'credit_committee' => 'Кредитный комитет',
    ];

    /** Ключ счётчика для текущего периода — именно он даёт сброс с 1 января. */
    public function periodKey(): string
    {
        return match ($this->reset_period) {
            'yearly'  => now()->format('Y'),
            'monthly' => now()->format('Y-m'),
            default   => 'all',
        };
    }

    /** Собрать номер из маски: {N} с паддингом, {YYYY}/{YY}/{MM}/{DD} и доп. токены. */
    public function format(int $value, array $extra = []): string
    {
        return strtr($this->mask, array_merge([
            '{N}'    => str_pad((string) $value, $this->padding, '0', STR_PAD_LEFT),
            '{YYYY}' => now()->format('Y'),
            '{YY}'   => now()->format('y'),
            '{MM}'   => now()->format('m'),
            '{DD}'   => now()->format('d'),
        ], $extra));
    }

    public function counters(): HasMany
    {
        return $this->hasMany(DocumentCounter::class);
    }

    /** Классификаторы, для которых этот нумератор задаёт нумерацию. */
    public function bindings(): HasMany
    {
        return $this->hasMany(NumeratorBinding::class);
    }

    /** Пользовательский нумератор (привязывается к классификаторам), а не глобальный поток. */
    public function isCustom(): bool
    {
        return $this->key === null;
    }

    public function allowsManualFor(User $user): bool
    {
        return $this->allow_manual
            && (empty($this->manual_roles) || $user->hasAnyRole($this->manual_roles));
    }
}
