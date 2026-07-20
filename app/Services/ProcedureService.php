<?php

namespace App\Services;

use App\Models\Procedure;
use App\Models\ProcedureChecklistItem;
use App\Models\ProcedureStage;
use App\Models\ProcedureStageRun;
use App\Models\ProcedureTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Движок процедур (ТЗ 19). Проходит авто-этапы автоматически и останавливается
 * на ручных, создавая активный run и уведомляя исполнителя. Развилка с негативным
 * вердиктом останавливает процедуру; после веера ждёт закрытия всех задач.
 */
class ProcedureService
{
    public function __construct(
        private NotificationService $notifications,
        private ProcedureNumberService $numbers,
        private AuditService $audit,
    ) {}

    /**
     * Завести процедуру по шаблону: заполнить первый этап-форму (данные + обязательные
     * вложения), пронумеровать и запустить движок.
     *
     * @param  UploadedFile[]  $files
     */
    public function start(ProcedureTemplate $template, User $initiator, string $title, ?string $formText, array $files): Procedure
    {
        return DB::transaction(function () use ($template, $initiator, $title, $formText, $files) {
            $procedure = Procedure::create([
                'procedure_template_id' => $template->id,
                'title'                 => $title,
                'initiator_id'          => $initiator->id,
                'status'                => 'draft',
                'current_position'      => 0,
                'data'                  => ['form' => $formText],
            ]);

            $this->numbers->assign($procedure);
            $this->materializeRuns($procedure, $template);

            $procedure->load('runs');
            $runs  = $procedure->runs;
            $first = $runs->first();

            // Если сценарий начинается с формы — она заполнена прямо при создании.
            if ($first && $first->type === 'form') {
                $this->attachFiles($procedure, $first, $files, $initiator);
                $first->update(['status' => 'done', 'executor_id' => $initiator->id, 'acted_by' => $initiator->id, 'acted_at' => now()]);
                $procedure->update(['current_position' => 1, 'status' => 'in_progress']);
            }

            $this->event($procedure, $initiator, 'created', null, ['number' => $procedure->number]);
            $this->audit->log('procedure_started', $procedure);

            $this->advance($procedure);

            return $procedure;
        });
    }

    /** Развернуть этапы шаблона в run-ы экземпляра (все pending, по порядку). */
    private function materializeRuns(Procedure $procedure, ProcedureTemplate $template): void
    {
        foreach ($template->stages()->orderBy('position')->get() as $i => $stage) {
            $procedure->runs()->create([
                'procedure_stage_id' => $stage->id,
                'position'           => $i,
                'type'               => $stage->type,
                'title'              => $stage->title,
                'status'             => 'pending',
            ]);
        }
    }

    /**
     * Продвинуть процедуру: проходим авто-этапы, останавливаемся на первом ручном
     * (делаем его активным и уведомляем исполнителя) либо завершаем процедуру.
     */
    public function advance(Procedure $procedure): void
    {
        if ($procedure->isDone()) {
            return;
        }

        $runs = $procedure->runs()->orderBy('position')->orderBy('id')->get();

        while (true) {
            $run = $runs[$procedure->current_position] ?? null;

            if (! $run) {
                $this->complete($procedure);
                return;
            }

            if (in_array($run->status, ['done', 'skipped'], true)) {
                $procedure->increment('current_position');
                continue;
            }

            $stage = $run->stage; // может быть null, если шаблон правился — тогда авто по type

            // Ручной этап — останавливаемся и ждём действия исполнителя.
            if (! in_array($run->type, ProcedureStage::AUTO_TYPES, true)) {
                $executorId = $this->resolveExecutor($procedure, $stage);
                $run->update(['status' => 'active', 'executor_id' => $executorId]);
                $procedure->update(['status' => $procedure->status === 'draft' ? 'in_progress' : $procedure->status]);

                if ($executorId && $executorId !== $procedure->initiator_id) {
                    $this->notifyUser($executorId, 'procedure_stage_assigned', $procedure, ['stage' => $run->title]);
                }
                return;
            }

            // Авто-этапы.
            if ($run->type === 'return_to_initiator') {
                $run->update(['status' => 'done', 'executor_id' => $procedure->initiator_id, 'acted_at' => now()]);
                $this->event($procedure, null, 'returned', null, ['stage' => $run->title]);
                $this->notifyUser($procedure->initiator_id, 'procedure_returned', $procedure, ['stage' => $run->title]);
                $procedure->increment('current_position');
                continue;
            }

            if ($run->type === 'fanout') {
                $this->fanOut($procedure);
                $run->update(['status' => 'done', 'acted_at' => now()]);
                $procedure->update(['status' => 'awaiting_tasks']);
                $procedure->increment('current_position');
                continue;
            }

            if ($run->type === 'completion') {
                if ($this->allTasksDone($procedure)) {
                    $run->update(['status' => 'done', 'acted_at' => now()]);
                    $procedure->increment('current_position');
                    continue;
                }
                // Ждём закрытия задач — курсор остаётся на этом этапе.
                $procedure->update(['status' => 'awaiting_tasks']);
                return;
            }

            // Неизвестный авто-тип — пропускаем, чтобы не зациклиться.
            $run->update(['status' => 'skipped']);
            $procedure->increment('current_position');
        }
    }

    /** Пройти ручной этап (форма/согласование): отчёт + вложения, дальше движок. */
    public function submitStage(ProcedureStageRun $run, User $actor, ?string $comment, array $files): void
    {
        $procedure = $run->procedure;

        DB::transaction(function () use ($run, $procedure, $actor, $comment, $files) {
            $this->attachFiles($procedure, $run, $files, $actor);
            $run->update(['status' => 'done', 'comment' => $comment, 'acted_by' => $actor->id, 'acted_at' => now()]);
            $this->event($procedure, $actor, $run->type === 'form' ? 'stage_submitted' : 'approved', $comment, ['stage' => $run->title]);
            $procedure->increment('current_position');
            $this->advance($procedure->fresh('runs'));
        });
    }

    /** Пройти развилку: положительный вердикт — дальше, отрицательный — остановка. */
    public function branch(ProcedureStageRun $run, User $actor, string $verdict, ?string $comment, array $files): void
    {
        $procedure = $run->procedure;

        DB::transaction(function () use ($run, $procedure, $actor, $verdict, $comment, $files) {
            $this->attachFiles($procedure, $run, $files, $actor);

            if ($verdict === 'negative') {
                $run->update(['status' => 'rejected', 'verdict' => 'negative', 'comment' => $comment, 'acted_by' => $actor->id, 'acted_at' => now()]);
                $procedure->update(['status' => 'stopped', 'stopped_reason' => $comment]);
                $this->event($procedure, $actor, 'stopped', $comment, ['stage' => $run->title]);
                $this->notifyUser($procedure->initiator_id, 'procedure_stopped', $procedure, ['stage' => $run->title, 'comment' => $comment]);
                return;
            }

            $run->update(['status' => 'done', 'verdict' => 'positive', 'comment' => $comment, 'acted_by' => $actor->id, 'acted_at' => now()]);
            $this->event($procedure, $actor, 'branched', $comment, ['stage' => $run->title]);
            $procedure->increment('current_position');
            $this->advance($procedure->fresh('runs'));
        });
    }

    /**
     * Заполнить чек-лист (ЧАСТЬ 1 — ответы на пресеты, ЧАСТЬ 2 — произвольные пункты
     * инициатора с выбранным исполнителем). Затем движок дойдёт до веера.
     *
     * @param  array  $presets  [item_id => ['answer' => ..., 'text' => ...]]
     * @param  array  $custom   [['title','field_type','answer','text','executor_id','spawns_task'], ...]
     */
    public function submitChecklist(ProcedureStageRun $run, User $actor, array $presets, array $custom): void
    {
        $procedure = $run->procedure;

        DB::transaction(function () use ($run, $procedure, $actor, $presets, $custom) {
            $pos      = 0;
            $template = $procedure->template;

            foreach ($template->checklistItems as $item) {
                $answer = $presets[$item->id] ?? [];
                $procedure->checklistEntries()->create([
                    'procedure_checklist_item_id' => $item->id,
                    'source'      => 'preset',
                    'position'    => $pos++,
                    'department'  => $item->department,
                    'title'       => $item->title,
                    'field_type'  => $item->field_type,
                    'options'     => $item->options,
                    'value'       => $this->normalizeAnswer($item->field_type, $answer),
                    'executor_id' => $this->resolveItemExecutor($procedure, $item),
                    'spawns_task' => $item->spawns_task,
                ]);
            }

            foreach ($custom as $row) {
                if (empty($row['title']) || empty($row['executor_id'])) {
                    continue;
                }
                $procedure->checklistEntries()->create([
                    'source'      => 'custom',
                    'position'    => $pos++,
                    'department'  => $row['department'] ?? null,
                    'title'       => $row['title'],
                    'field_type'  => $row['field_type'] ?? 'boolean',
                    'options'     => null,
                    'value'       => $this->normalizeAnswer($row['field_type'] ?? 'boolean', $row),
                    'executor_id' => (int) $row['executor_id'],
                    'spawns_task' => (bool) ($row['spawns_task'] ?? true),
                ]);
            }

            $run->update(['status' => 'done', 'acted_by' => $actor->id, 'acted_at' => now()]);
            $this->event($procedure, $actor, 'checklist_filled', null, ['count' => $pos]);
            $procedure->increment('current_position');
            $this->advance($procedure->fresh('runs'));
        });
    }

    /** Веерное порождение задач по активным пунктам чек-листа (ЭТАП 6). */
    private function fanOut(Procedure $procedure): void
    {
        $count = 0;

        foreach ($procedure->checklistEntries as $entry) {
            if (! $entry->spawns_task || ! $entry->isActive() || ! $entry->executor_id) {
                continue;
            }

            $task = $procedure->tasks()->create([
                'procedure_checklist_entry_id' => $entry->id,
                'title'       => $entry->title,
                'description' => $entry->answerLabel(),
                'assignee_id' => $entry->executor_id,
                'status'      => 'pending',
            ]);
            $count++;

            $this->notifyUser($entry->executor_id, 'procedure_task_assigned', $procedure, ['task' => $task->title]);
        }

        $this->event($procedure, null, 'tasks_generated', null, ['count' => $count]);
        $this->audit->log('procedure_tasks_generated', $procedure);
    }

    /** Перепроверить завершение после закрытия очередной задачи. */
    public function checkCompletion(Procedure $procedure): void
    {
        if ($procedure->status === 'awaiting_tasks') {
            $this->advance($procedure->fresh('runs'));
        }
    }

    private function complete(Procedure $procedure): void
    {
        $procedure->update(['status' => 'completed']);
        $this->event($procedure, null, 'completed', null, []);
        $this->audit->log('procedure_completed', $procedure);
        $this->notifyUser($procedure->initiator_id, 'procedure_completed', $procedure, []);
    }

    private function allTasksDone(Procedure $procedure): bool
    {
        return ! $procedure->tasks()->where('status', '!=', 'done')->exists();
    }

    // --- Исполнители ---

    private function resolveExecutor(Procedure $procedure, ?ProcedureStage $stage): ?int
    {
        if (! $stage) {
            return $procedure->initiator_id;
        }

        return match ($stage->executor_mode) {
            'user' => $stage->executor_user_id,
            'role' => $this->firstUserWithRole($stage->executor_role),
            default => $procedure->initiator_id, // initiator
        };
    }

    private function resolveItemExecutor(Procedure $procedure, ProcedureChecklistItem $item): ?int
    {
        return match ($item->executor_mode) {
            'user'            => $item->executor_user_id,
            'department_head' => $procedure->initiator->department?->head_user_id,
            default           => $procedure->initiator_id, // initiator
        };
    }

    private function firstUserWithRole(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return User::where('is_active', true)
            ->where(fn ($q) => $q->where('role', $code)
                ->orWhereHas('roles', fn ($r) => $r->where('code', $code)))
            ->orderBy('id')
            ->value('id');
    }

    /** Ответ пункта в единый вид ['answer' => bool|string, 'text' => ?string]. */
    private function normalizeAnswer(string $fieldType, array $raw): array
    {
        $answer = $raw['answer'] ?? null;

        return match ($fieldType) {
            'checkbox', 'boolean', 'boolean_text' => [
                'answer' => (bool) filter_var($answer, FILTER_VALIDATE_BOOLEAN),
                'text'   => $raw['text'] ?? null,
            ],
            default => ['answer' => is_string($answer) ? trim($answer) : $answer, 'text' => null],
        };
    }

    // --- Вспомогательное ---

    private function attachFiles(Procedure $procedure, ProcedureStageRun $run, array $files, User $uploader): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $procedure->files()->create([
                'procedure_stage_run_id' => $run->id,
                'uploaded_by'            => $uploader->id,
                'original_name'          => $file->getClientOriginalName(),
                'path'                   => $file->store("procedures/{$procedure->id}"),
                'size'                   => $file->getSize(),
                'mime'                   => $file->getClientMimeType(),
            ]);
        }
    }

    private function event(Procedure $procedure, ?User $user, string $type, ?string $comment, ?array $meta): void
    {
        $procedure->events()->create([
            'user_id' => $user?->id,
            'type'    => $type,
            'comment' => $comment,
            'meta'    => $meta,
        ]);
    }

    private function notifyUser(int $userId, string $type, Procedure $procedure, array $extra): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        $this->notifications->notify($user, $type, array_merge([
            'title'        => $procedure->title,
            'procedure_id' => $procedure->id,
        ], $extra));
    }
}
