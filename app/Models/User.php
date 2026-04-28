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
        'name', 'email', 'password', 'role', 'position', 'department_id',
        'bitrix24_id', 'bitrix24_token', 'avatar', 'is_active',
        'notification_email', 'telegram_chat_id',
    ];

    protected $hidden = ['password', 'remember_token', 'bitrix24_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'notification_email' => 'boolean',
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

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=5B4FE8&color=fff';
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'admin'    => 'Администратор',
            'director' => 'Директор',
            'linear'   => 'Линейный менеджер',
            'archiver' => 'Архивариус',
            default    => $this->role,
        };
    }
}
