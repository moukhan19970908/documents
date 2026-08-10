<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'role_level', 'role_title',
        'position', 'department_id', 'manager_id',
        'bitrix24_id', 'bitrix24_token', 'avatar', 'is_active',
        'notification_email', 'telegram_chat_id', 'agreement_accepted_at',
        'permissions','workflow_access_level','tasks_access_level','archive_access_level',
    ];

    protected $hidden = ['password', 'remember_token', 'bitrix24_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'is_active'               => 'boolean',
            'notification_email'      => 'boolean',
            'agreement_accepted_at'   => 'datetime',
            'permissions'            => 'array',
            'workflow_access_level'  => 'string',
            'tasks_access_level'     => 'string',
            'archive_access_level'   => 'string',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Additional roles beyond the primary one stored in users.role. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Every role code this user holds: the primary role (users.role) plus
     * every role attached through the role_user pivot.
     *
     * @return array<int, string>
     */
    public function roleCodes(): array
    {
        $codes = $this->roles->pluck('code')->all();

        if ($this->role) {
            $codes[] = $this->role;
        }

        return array_values(array_unique($codes));
    }

    public function hasRole(string $code): bool
    {
        return in_array($code, $this->roleCodes(), true);
    }

    /** @param array<int, string> $codes */
    public function hasAnyRole(array $codes): bool
    {
        return (bool) array_intersect($codes, $this->roleCodes());
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'initiator_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(DocumentApprovalDecision::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notificationLogs()->whereNull('read_at')->count();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isExternal(): bool
    {
        return $this->hasRole('external');
    }

    /**
     * Resolve effective workflow access level for this user.
     * Priority: individual setting > department setting > 'full' (backwards-compatible default)
     */
    public function resolveWorkflowAccess(): string
    {
        if ($this->isAdmin()) {
            return 'full';
        }

        // External participants only ever see their own documents.
        if ($this->isExternal()) {
            return 'own';
        }

        if ($this->workflow_access_level !== null) {
            return $this->workflow_access_level;
        }

        // Fall back to department-level setting
        $dept = $this->relationLoaded('department')
            ? $this->department
            : Department::find($this->department_id);

        if ($dept && $dept->workflow_access_level !== null) {
            return $dept->workflow_access_level;
        }

        // Default: full access (backwards-compatible for existing users)
        return 'full';
    }

    public function resolveTasksAccess(): string
    {
        if ($this->isAdmin()) {
            return 'full';
        }

        if ($this->isExternal()) {
            return 'own';
        }

        if ($this->tasks_access_level !== null) {
            return $this->tasks_access_level;
        }

        $dept = $this->relationLoaded('department')
            ? $this->department
            : Department::find($this->department_id);

        if ($dept && $dept->tasks_access_level !== null) {
            return $dept->tasks_access_level;
        }

        return 'full';
    }

    public function resolveArchiveAccess(): string
    {
        if ($this->isAdmin()) {
            return 'full';
        }

        if ($this->isExternal()) {
            return 'own';
        }

        if ($this->archive_access_level !== null) {
            return $this->archive_access_level;
        }

        $dept = $this->relationLoaded('department')
            ? $this->department
            : Department::find($this->department_id);

        if ($dept && $dept->archive_access_level !== null) {
            return $dept->archive_access_level;
        }

        return 'full';
    }

    /**
     * Check whether this user has been explicitly granted a permission.
     * Admins always pass. When no permissions are set (null) the user is
     * not restricted — the gate only activates once permissions are saved.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->permissions === null) {
            return true; // no explicit restrictions set yet
        }

        return in_array($permission, $this->permissions, true);
    }

    /**
     * Виден ли пользователю пункт меню по матрице прав.
     * Админ видит всё; иначе — если хотя бы одна роль пользователя
     * имеет это право в role_permissions.
     */
    public function canSeeMenu(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) array_intersect(
            \App\Support\Permissions::grantedRoleCodes($key),
            $this->roleCodes()
        );
    }

    /**
     * Право из матрицы «Роли и доступы» (config/permissions.php → role_permissions).
     * Админ имеет любое право.
     */
    public function hasMatrixPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) array_intersect(
            \App\Support\Permissions::grantedRoleCodes($key),
            $this->roleCodes()
        );
    }

    /** Узкое право издания приказов (ТЗ 16.1). */
    public function canIssueOrders(): bool
    {
        return $this->hasMatrixPermission('orders.issue');
    }

    public function isManager(): bool
    {
        return ($this->role_level ?? 1) >= 2
            || $this->hasAnyRole(['admin', 'director'])
            || $this->headsAnyDepartment();
    }

    private ?bool $headsAnyDepartmentCache = null;

    /** Руководит ли пользователь хотя бы одним отделом (head_user_id) — по оргструктуре. */
    public function headsAnyDepartment(): bool
    {
        return $this->headsAnyDepartmentCache ??= Department::where('head_user_id', $this->id)->exists();
    }

    /**
     * ID сотрудников в отделах, которыми руководит пользователь (как head_user_id),
     * включая поддеревья. Пусто, если он не руководитель ни одного отдела.
     *
     * @return array<int, int>
     */
    public function headedDepartmentUserIds(): array
    {
        $headed = Department::where('head_user_id', $this->id)->pluck('id');
        if ($headed->isEmpty()) {
            return [];
        }

        $deptIds = $headed->flatMap(fn ($id) => Department::getDescendantIds($id))->unique()->all();

        return User::whereIn('department_id', $deptIds)->pluck('id')->all();
    }

    /** Ранг сотрудника для доступа к Базе знаний: 3 — директор, 2 — руководитель, 1 — сотрудник. */
    public function knowledgeRank(): int
    {
        return match (true) {
            $this->hasAnyRole(['admin', 'director']) || ($this->role_level ?? 1) >= 3 => 3,
            $this->isManager()                                                        => 2,
            default                                                                   => 1,
        };
    }

    /**
     * Whether this user is named as a specific approver in any active route step.
     * Optionally scoped to a request type (trip, vacation, vacation_registry).
     */
    public function isApprover(?string $requestType = null): bool
    {
        return ApprovalRouteStep::where('approver_user_id', $this->id)
            ->when($requestType, fn ($q) => $q->whereHas('route', fn ($r) =>
                $r->where('request_type', $requestType)->where('is_active', true)
            ))
            ->exists();
    }

    /** Сотрудник бухгалтерии — работает в отделе с флагом «бухгалтерия» (или его поддереве). */
    public function isAccounting(): bool
    {
        return $this->department_id !== null
            && in_array($this->department_id, \App\Models\Department::accountingDepartmentIds(), true);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function directSubordinateIds(): array
    {
        return $this->subordinates()->pluck('id')->toArray();
    }

    public function allSubordinateIds(): array
    {
        $ids = [];
        $queue = [$this->id];
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $children = User::where('manager_id', $currentId)->pluck('id')->toArray();
            $ids = array_merge($ids, $children);
            $queue = array_merge($queue, $children);
        }
        return $ids;
    }

    public function tripRequests(): HasMany
    {
        return $this->hasMany(TripRequest::class);
    }

    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            // Аватарки лежат в приватном бакете S3 (Bitrix24Service::downloadAvatar).
            // Прямой ->url() отдаёт публичную ссылку, которую MinIO закрывает 403 —
            // поэтому подписываем временную ссылку: её браузер грузит напрямую.
            return Storage::disk('s3')->temporaryUrl($this->avatar, now()->addHour());
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=5B4FE8&color=fff';
    }

    public function getRoleLabelAttribute(): string
    {
        if ($this->role_title) {
            return $this->role_title;
        }
        return match($this->role) {
            'admin'    => 'Администратор',
            'external' => 'Внешний участник',
            default    => $this->position ?? '',
        };
    }
}
