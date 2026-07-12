<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'slug', 'icon', 'description', 'is_active',
        'name_template', 'numerator_id', 'default_workflow_id',
        'allowed_departments', 'allowed_users', 'allowed_roles',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'allowed_departments' => 'array',
        'allowed_users'       => 'array',
        'allowed_roles'       => 'array',
    ];

    /** Roles that may be granted the right to create documents of a type. */
    public const CREATOR_ROLES = [
        'linear'   => 'Линейный сотрудник',
        'director' => 'Руководитель / директор',
        'archiver' => 'Архивариус',
        'admin'    => 'Администратор',
    ];

    public const ICONS = ['document', 'contract', 'order', 'letter'];

    public function defaultWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'default_workflow_id');
    }

    public function numerator(): BelongsTo
    {
        return $this->belongsTo(Numerator::class);
    }

    public function subtypes(): HasMany
    {
        return $this->hasMany(DocumentSubtype::class)->orderBy('sort_order');
    }

    /** Attributes of the type itself — subtype-specific ones are excluded. */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class)
            ->whereNull('document_subtype_id')
            ->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Types this user may create documents of. Departments and roles narrow the type down;
     * a user named explicitly gets access regardless of them.
     */
    public function isAvailableFor(User $user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!empty($this->allowed_users) && in_array($user->id, $this->allowed_users)) {
            return true;
        }

        if (!empty($this->allowed_departments) && !in_array($user->department_id, $this->allowed_departments)) {
            return false;
        }

        if (!empty($this->allowed_roles) && !in_array($user->role, $this->allowed_roles)) {
            return false;
        }

        return true;
    }
}
