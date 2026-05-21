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
