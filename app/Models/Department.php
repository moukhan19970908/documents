<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'head_user_id', 'bitrix24_department_id', 'workflow_access_level', 'tasks_access_level', 'archive_access_level', 'cross_visibility'];

    protected function casts(): array
    {
        return ['cross_visibility' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * ID корневого департамента (направления) для заданного отдела:
     * поднимаемся по parent_id до вершины.
     */
    public static function directionRootId(int $deptId): int
    {
        $all = static::all(['id', 'parent_id'])->keyBy('id');
        $id  = $deptId;

        while (isset($all[$id]) && $all[$id]->parent_id !== null) {
            $id = $all[$id]->parent_id;
        }

        return $id;
    }

    /**
     * ID отделов, документы которых видны сотруднику этого отдела при доступе
     * уровня «department». Обычно — свой поддерев; если у направления (корня)
     * включена кросс-видимость, расширяется до всего направления.
     *
     * @return array<int, int>
     */
    public static function visibleScopeIds(int $deptId): array
    {
        $rootId  = static::directionRootId($deptId);
        $scopeId = ($rootId !== $deptId && static::where('id', $rootId)->value('cross_visibility'))
            ? $rootId
            : $deptId;

        return static::getDescendantIds($scopeId);
    }

    /**
     * Returns IDs of the given department and all its descendants (recursive).
     */
    public static function getDescendantIds(int $parentId): array
    {
        $all    = static::all(['id', 'parent_id']);
        $result = [];
        $queue  = [$parentId];

        while (!empty($queue)) {
            $current  = array_shift($queue);
            $result[] = $current;
            $children = $all->where('parent_id', $current)->pluck('id')->toArray();
            $queue    = array_merge($queue, $children);
        }

        return $result;
    }
}
