<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'direction_id', 'is_direction', 'head_user_id', 'bitrix24_department_id', 'workflow_access_level', 'tasks_access_level', 'archive_access_level', 'cross_visibility', 'is_accounting'];

    protected function casts(): array
    {
        return ['cross_visibility' => 'boolean', 'is_accounting' => 'boolean', 'is_direction' => 'boolean'];
    }

    /**
     * ID всех бухгалтерских отделов: отделы с флагом is_accounting плюс всё их
     * направление (поддерево по parent_id и прикреплённые через direction_id отделы).
     * Реестры, переданные в бухгалтерию, видит любой сотрудник из этого множества (общий пул).
     *
     * @return array<int, int>
     */
    public static function accountingDepartmentIds(): array
    {
        $flagged = static::where('is_accounting', true)->pluck('id');

        return $flagged->flatMap(fn ($id) => static::directionMemberIds($id))->unique()->values()->all();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /** Направление, в которое отдел добавлен вручную (слой поверх оргструктуры). */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'direction_id');
    }

    /** Отделы, добавленные в это направление (по direction_id, без переноса в дереве). */
    public function members(): HasMany
    {
        return $this->hasMany(Department::class, 'direction_id');
    }

    /**
     * Отделы направления для списков-каталогов: дочерние по дереву (parent_id)
     * плюс добавленные вручную (direction_id). Требует загруженных children и members.
     */
    public function catalogDepartments(): \Illuminate\Support\Collection
    {
        return $this->children->concat($this->members)->unique('id')->sortBy('name')->values();
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
     * ID направления для заданного отдела. Поднимаемся по parent_id; если на пути
     * встречается отдел с явным direction_id (добавлен в направление вручную) —
     * это и есть его направление. Иначе направление = корень дерева (parent_id).
     */
    public static function directionRootId(int $deptId): int
    {
        $all = static::all(['id', 'parent_id', 'direction_id'])->keyBy('id');
        $id  = $deptId;

        while (isset($all[$id])) {
            if ($all[$id]->direction_id !== null) {
                return (int) $all[$id]->direction_id;
            }
            if ($all[$id]->parent_id === null) {
                break;
            }
            $id = $all[$id]->parent_id;
        }

        return $id;
    }

    /**
     * ID всех отделов направления: сам отдел-направление и добавленные в него
     * отделы (direction_id) вместе с их поддеревьями по parent_id. Отделы,
     * явно принадлежащие другому направлению, в поддерево не спускаем.
     *
     * Является надмножеством getDescendantIds(): для обычного отдела без
     * прикреплённых членов результат совпадает.
     *
     * @return array<int, int>
     */
    public static function directionMemberIds(int $directionId): array
    {
        $all = static::all(['id', 'parent_id', 'direction_id']);

        $queue = [$directionId];
        foreach ($all as $d) {
            if ((int) $d->direction_id === $directionId) {
                $queue[] = $d->id;
            }
        }

        $result = [];
        $seen   = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $result[] = $current;

            foreach ($all as $d) {
                if ((int) $d->parent_id !== $current) {
                    continue;
                }
                $childDir = $d->direction_id !== null ? (int) $d->direction_id : null;
                if ($childDir === null || $childDir === $directionId) {
                    $queue[] = $d->id;
                }
            }
        }

        return $result;
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
        $rootId = static::directionRootId($deptId);

        if ($rootId !== $deptId && static::where('id', $rootId)->value('cross_visibility')) {
            return static::directionMemberIds($rootId);
        }

        return static::getDescendantIds($deptId);
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
