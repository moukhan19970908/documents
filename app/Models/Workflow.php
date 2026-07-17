<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'document_type_id', 'created_by', 'is_system', 'is_active',
        'approval_type', 'allowed_departments', 'allowed_users', 'process_fields',
        'process_type', 'icon', 'owner_id', 'status', 'allow_file_upload',
        'engine_version', 'is_version', 'parent_workflow_id', 'version_label', 'published_at',
    ];

    protected $casts = [
        'is_system'           => 'boolean',
        'is_active'           => 'boolean',
        'allow_file_upload'   => 'boolean',
        'is_version'          => 'boolean',
        'published_at'        => 'datetime',
        'allowed_departments' => 'array',
        'allowed_users'       => 'array',
        'process_fields'      => 'array',
    ];

    /** Process type decides which blocks the route builder offers. */
    public const PROCESS_TYPES = [
        'document_flow'    => 'Документооборот',
        'orders'           => 'Приказы',
        'assignments'      => 'Поручения',
        'procedures'       => 'Процедуры',
        'checks'           => 'Проверки',
        'requests'         => 'Заявки',
        'credit_committee' => 'Кредитный комитет',
    ];

    public const ICONS = ['document', 'chat', 'briefcase', 'shield', 'bank'];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Launch parameters — the questions whose answers shape the route. */
    public function parameters(): HasMany
    {
        return $this->hasMany(WorkflowParameter::class)->orderBy('sort_order');
    }

    /** Subtypes of the classifier this scenario serves. */
    public function subtypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentSubtype::class, 'document_subtype_workflow');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** v2 scenarios run from immutable published copies, so editing never touches live processes. */
    public function isV2(): bool
    {
        return (int) $this->engine_version === 2;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Workflow::class, 'parent_workflow_id')->orderByDesc('id');
    }

    public function publishedVersion(): ?Workflow
    {
        return $this->versions()->whereNotNull('published_at')->first();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'parent_workflow_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sort_order');
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowFolder::class, 'workflow_folder_workflow');
    }

    /** Бланки, которые сценарий предлагает заполнить вместо готового файла. */
    public function blankTemplates(): BelongsToMany
    {
        return $this->belongsToMany(BlankTemplate::class, 'blank_template_workflow');
    }

    /** Сценарию без бланков заполнять нечего — файл остаётся единственным способом принести документ. */
    public function allowsFileUpload(): bool
    {
        return $this->blankTemplates->isEmpty() || $this->allow_file_upload;
    }

    /**
     * Кто может запускать документы по этому сценарию. Права выдаются отделу либо отдельным
     * сотрудникам дополнительно: доступ есть у того, чей отдел разрешён, либо кто добавлен лично.
     * Ничего не задано — сценарий доступен всем.
     */
    public function isLaunchableBy(User $user): bool
    {
        $departments = $this->allowed_departments ?? [];
        $users = $this->allowed_users ?? [];

        if (empty($departments) && empty($users)) {
            return true;
        }

        return in_array($user->department_id, $departments) || in_array($user->id, $users);
    }
}
