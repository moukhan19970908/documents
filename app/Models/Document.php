<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'number', 'registered_at', 'document_type_id', 'document_subtype_id',
        'workflow_id', 'initiator_id', 'blank_template_id', 'body_html',
        'current_stage_id', 'status', 'data', 'bitrix24_task_id', 'deadline_at',
    ];

    protected $casts = [
        'data'          => 'array',
        'deadline_at'   => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function subtype(): BelongsTo
    {
        return $this->belongsTo(DocumentSubtype::class, 'document_subtype_id');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(DocumentRegistration::class);
    }

    /** Бланк, по которому заполнено тело документа. */
    public function blank(): BelongsTo
    {
        return $this->belongsTo(BlankTemplate::class, 'blank_template_id');
    }

    public function isRegistered(): bool
    {
        return $this->number !== null;
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /** withTrashed: звено могли убрать из маршрута — карточка документа должна его показывать. */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id')->withTrashed();
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function currentFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)->where('is_current', true)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    /**
     * Документы, где участвует пользователь.
     * scope: participant — инициатор или согласующий; initiator — только инициатор;
     * approver — только согласующий.
     */
    public function scopeParticipatedBy($query, int $userId, string $scope = 'participant')
    {
        return $query->where(function ($q) use ($userId, $scope) {
            $includeInitiator = $scope !== 'approver';
            $includeApprover  = $scope !== 'initiator';

            if ($includeInitiator) {
                $q->where('initiator_id', $userId);
            }

            if ($includeApprover) {
                $method = $includeInitiator ? 'orWhereHas' : 'whereHas';
                $q->{$method}('approvals.stages.workflowStage.approvers',
                    fn ($a) => $a->where('approver_id', $userId));
            }
        });
    }

    public function activeApproval(): HasOne
    {
        return $this->hasOne(DocumentApproval::class)->where('status', 'in_progress')->latestOfMany();
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(DocumentApproval::class)->latestOfMany();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DocumentNote::class)->latest();
    }

    public function relatedFiles(): HasMany
    {
        return $this->hasMany(DocumentRelatedFile::class)->latest();
    }

    /** @var array<string,int>|null Мемоизация прогресса ознакомления на время запроса. */
    private ?array $ackProgressCache = null;

    /**
     * Прогресс фазы ознакомления: ['done' => x, 'total' => y], либо null, если фазы нет.
     * Считается по задачам ознакомления — они закрываются в момент ознакомления участника.
     */
    public function ackProgress(): ?array
    {
        if ($this->ackProgressCache === null) {
            $rows = Task::where('document_id', $this->id)
                ->whereIn('status', ['pending', 'completed'])
                ->whereHas('stage.workflowStage', fn ($q) => $q->where('phase', 'ack'))
                ->get(['status']);

            $this->ackProgressCache = $rows->isEmpty() ? [] : [
                'done'  => $rows->where('status', 'completed')->count(),
                'total' => $rows->count(),
            ];
        }

        return $this->ackProgressCache ?: null;
    }

    /**
     * Согласование пройдено, но ознакомились ещё не все: маршрут не держим, однако
     * документ не должен выглядеть «Одобрено», пока фаза ознакомления не закрыта.
     */
    public function awaitingAck(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        $progress = $this->ackProgress();

        return $progress && $progress['done'] < $progress['total'];
    }

    /**
     * Звено ознакомления, по которому у участника ещё висит незакрытая задача.
     * Ознакомление не держит маршрут — звено уже «approved», поэтому обычный
     * activeStage() его не находит; для показа кнопки «Ознакомлен» ищем по задаче.
     */
    public function myPendingAckStage(int $userId): ?DocumentApprovalStage
    {
        $stageId = Task::where('document_id', $this->id)
            ->where('assignee_id', $userId)
            ->where('status', 'pending')
            ->whereHas('stage.workflowStage', fn ($q) => $q->where('phase', 'ack'))
            ->value('document_approval_stage_id');

        return $stageId
            ? DocumentApprovalStage::with(['workflowStage.approvers', 'decisions'])->find($stageId)
            : null;
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->awaitingAck()) {
            $progress = $this->ackProgress();

            return "На ознакомлении ({$progress['done']}/{$progress['total']})";
        }

        return match($this->status) {
            'draft'            => 'Черновик',
            'in_review'        => 'На одобрении',
            'requires_changes' => 'Требует изменений',
            'approved'         => 'Одобрено',
            'signed'           => 'Подписано',
            'archived'         => 'Архив',
            default            => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->awaitingAck()) {
            return 'amber';
        }

        return match($this->status) {
            'draft'            => 'gray',
            'in_review'        => 'blue',
            'requires_changes' => 'red',
            'approved'         => 'green',
            'signed'           => 'indigo',
            'archived'         => 'gray',
            default            => 'gray',
        };
    }
}
