<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function unreadNotificationsCount(): int
    {
        return $this->notificationLogs()->whereNull('read_at')->count();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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

    public function isManager(): bool
    {
        return ($this->role_level ?? 1) >= 2 || in_array($this->role, ['admin', 'director']);
    }

    public function isAccounting(): bool
    {
        return $this->role === 'archiver'; // mapping archiver → accounting role
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
            return asset('storage/' . $this->avatar);
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
            'director' => 'Директор',
            'linear'   => 'Линейный менеджер',
            'archiver' => 'Архивариус',
            default    => $this->role,
        };
    }
}
