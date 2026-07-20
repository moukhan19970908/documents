<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    protected $fillable = [
        'title', 'type', 'study_minutes', 'body',
        'direction_id', 'department_id', 'level',
        'is_general', 'access_level', 'is_published', 'author_id',
    ];

    protected $casts = [
        'is_general'   => 'boolean',
        'is_published' => 'boolean',
    ];

    /** Типы материала (редактор). */
    public const TYPES = [
        'article'     => 'Статья',
        'video'       => 'Видео',
        'instruction' => 'Инструкция',
        'regulation'  => 'Регламент',
    ];

    /** Уровни иерархии — и для размещения, и для доступа. */
    public const LEVELS = [
        'employees' => 'Сотрудники',
        'managers'  => 'Руководители',
        'directors' => 'Директора',
    ];

    /** Подписи уровней для размещения в дереве. */
    public const LEVEL_PLACEMENT = [
        'employees' => 'Для сотрудников',
        'managers'  => 'Для руководителей отдела',
        'directors' => 'Для директоров',
    ];

    /** Ранги уровней: чем выше, тем шире доступ (директор видит всё нижестоящее). */
    private const LEVEL_RANK = ['employees' => 1, 'managers' => 2, 'directors' => 3];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'direction_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Отделы, которым открыт доступ (правило по структуре). */
    public function accessDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'material_department');
    }

    /** Сотрудники с точечным доступом. */
    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'material_user');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? '';
    }

    /** «7 мин видео» / «5 мин чтения». */
    public function studyLabel(): ?string
    {
        if (!$this->study_minutes) {
            return null;
        }

        return $this->study_minutes . ' мин ' . ($this->type === 'video' ? 'видео' : 'чтения');
    }

    /**
     * Виден ли материал сотруднику.
     *   — админ видит всё;
     *   — «Общее для всех» — всем;
     *   — точечный доступ — да;
     *   — иначе: отдел сотрудника входит в отделы доступа (с потомками)
     *            И его уровень не ниже требуемого.
     */
    public function visibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$this->is_published) {
            return false;
        }

        if ($this->is_general) {
            return true;
        }

        if ($this->allowedUsers->contains('id', $user->id)) {
            return true;
        }

        if (!$user->department_id) {
            return false;
        }

        $scope = [];
        foreach ($this->accessDepartments as $dept) {
            $scope = array_merge($scope, Department::getDescendantIds($dept->id));
        }

        if (!in_array($user->department_id, $scope, true)) {
            return false;
        }

        return $user->knowledgeRank() >= (self::LEVEL_RANK[$this->access_level] ?? 1);
    }
}
