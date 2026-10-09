<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Правила проверок (ТЗ 20). Круг инициаторов узкий (аппарат управления, ГД,
 * контрольные органы) — они и так орг-широкие, поэтому проверять можно любые
 * звенья. Подпроверки создаёт исполнитель (проверяющий) узла.
 */
class InspectionService
{
    private const MAX_DEPTH = 5;

    /** Может ли пользователь инициировать корневые проверки. */
    public function canInitiate(User $user): bool
    {
        return $user->hasMatrixPermission('inspections.issue');
    }

    /** Может ли исполнитель узла создать подпроверку. */
    public function canSubRequest(User $user, Inspection $node): bool
    {
        return ($node->executor_id === $user->id || $user->isAdmin())
            && $node->depth < self::MAX_DEPTH
            && $node->status !== 'done';
    }

    /**
     * Кого можно назначить проверяющим — все активные сотрудники (кроме себя).
     * Область не сужается: проверки инициируют орг-широкие роли.
     *
     * @return Collection<int, User>
     */
    public function assignableExecutors(User $initiator): Collection
    {
        return User::where('is_active', true)
            ->where('id', '!=', $initiator->id)
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department_id']);
    }

    /**
     * Удалить узел вместе со всеми подпроверками: без этого они остались бы в базе без родителя
     * (parent_id обнуляется). Файлы удаляемых узлов убираются из хранилища и из «Собранных
     * материалов» предков — при приёмке туда копируются записи с тем же путём.
     *
     * @return int сколько узлов удалено
     */
    public function deleteWithSubtree(Inspection $node): int
    {
        $ids = [$node->id];
        for ($level = [$node->id]; $level !== []; ) {
            $level = Inspection::whereIn('parent_id', $level)->pluck('id')->all();
            array_push($ids, ...$level);
        }

        $paths = InspectionFile::whereIn('inspection_id', $ids)
            ->whereNull('source_inspection_id')
            ->pluck('path')->filter()->unique()->values()->all();

        // События и файлы удаляемых узлов уходят каскадом.
        DB::transaction(function () use ($ids, $paths) {
            InspectionFile::whereIn('path', $paths)->delete();
            Inspection::whereIn('id', $ids)->delete();
        });

        Storage::delete($paths);

        return count($ids);
    }
}
