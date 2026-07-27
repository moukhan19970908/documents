<?php

namespace App\Http\Controllers;

use App\Models\ArchivedDocument;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArchiveController extends Controller
{
    /** Точки входа в архив (ТЗ 26.1). */
    private const VIEWS = ['all', 'directions', 'types', 'counterparties', 'cases_orders', 'cases_assignments'];

    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $user   = auth()->user();
        $access = $user->resolveArchiveAccess();

        if ($access === 'none') {
            abort(403, 'Нет доступа к архиву.');
        }

        $view = in_array($request->get('view'), self::VIEWS, true) ? $request->get('view') : 'all';

        // Базовая выборка в пределах доступа — переиспользуется для списка и фасетов.
        $scoped = fn () => $this->scoped($user, $access);

        $query = $scoped()->with(['initiator', 'type'])->latest('archived_at');

        // Ограничение точки входа «Дела».
        if ($view === 'cases_orders') {
            $query->where('source_type', Order::class);
        } elseif ($view === 'cases_assignments') {
            $query->where('source_type', Assignment::class);
        }

        // Выбор внутри среза.
        if ($direction = $request->integer('direction') ?: null) {
            $query->where('direction_id', $direction);
        }
        if ($department = $request->integer('department') ?: null) {
            $query->whereIn('department_id', Department::getDescendantIds($department));
        }
        if ($type = $request->integer('type') ?: null) {
            $query->where('document_type_id', $type);
        }
        if (($counterparty = $request->get('counterparty')) !== null && $counterparty !== '') {
            $query->where('counterparty', $counterparty);
        }

        // Общие фильтры.
        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")
                ->orWhere('counterparty', 'like', "%{$search}%"));
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('archived_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('archived_at', '<=', $dateTo);
        }
        if ($author = $request->integer('author') ?: null) {
            $query->where('initiator_id', $author);
        }

        $documents = $query->paginate(25)->withQueryString();

        $facets = $this->facets($scoped, $view, $direction);

        $storageUsed    = (int) $scoped()->sum('file_size');
        $storageTotal   = 10 * 1024 * 1024 * 1024; // 10 ГБ
        $storagePercent = min(100, $storageTotal > 0 ? round($storageUsed / $storageTotal * 100) : 0);

        return view('archive.index', array_merge($facets, [
            'documents'      => $documents,
            'view'           => $view,
            'storagePercent' => $storagePercent,
            'storageUsed'    => $storageUsed,
        ]));
    }

    /** Карточка дела: приказ + ознакомления, поручение + подпоручения, документ + участники. */
    public function show(ArchivedDocument $archived)
    {
        $this->authorizeAccess($archived);
        $this->audit->log('archive_viewed', $archived);

        $archived->loadMissing(['initiator', 'type', 'direction', 'department']);

        $openUrl = match ($archived->source_type) {
            \App\Models\Document::class => route('documents.show', $archived->source_id),
            Order::class                => route('orders.show', $archived->source_id),
            Assignment::class           => route('assignments.show', $archived->source_id),
            default                     => null,
        };

        return view('archive.show', compact('archived', 'openUrl'));
    }

    /** Скачать неизменяемую копию файла. */
    public function file(ArchivedDocument $archived)
    {
        $this->authorizeAccess($archived);
        abort_unless($archived->file_path && Storage::exists($archived->file_path), 404);

        $this->audit->log('archive_file_downloaded', $archived);

        return Storage::download($archived->file_path, $archived->file_name ?? 'document');
    }

    /** Скачать неизменяемый лист согласования. */
    public function sheet(ArchivedDocument $archived)
    {
        $this->authorizeAccess($archived);
        abort_unless($archived->approval_sheet_path && Storage::exists($archived->approval_sheet_path), 404);

        $this->audit->log('archive_sheet_downloaded', $archived);

        return Storage::download($archived->approval_sheet_path, "Лист_согласования_{$archived->id}.pdf");
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Фасеты (счётчики) для текущей точки входа, в пределах доступа. */
    private function facets(callable $scoped, string $view, ?int $direction): array
    {
        // Направления.
        $dirCounts = $scoped()->selectRaw('direction_id, COUNT(*) c')->groupBy('direction_id')->pluck('c', 'direction_id');
        $directions = Department::whereIn('id', $dirCounts->keys()->filter())->orderBy('name')->get();
        foreach ($directions as $d) {
            $d->archive_count = $dirCounts[$d->id] ?? 0;
        }

        // Отделы выбранного направления.
        $departments = collect();
        if ($direction) {
            $deptCounts = $scoped()->where('direction_id', $direction)
                ->selectRaw('department_id, COUNT(*) c')->groupBy('department_id')->pluck('c', 'department_id');
            $departments = Department::whereIn('id', $deptCounts->keys()->filter())->orderBy('name')->get();
            foreach ($departments as $d) {
                $d->archive_count = $deptCounts[$d->id] ?? 0;
            }
        }

        // Типы.
        $typeCounts = $scoped()->selectRaw('document_type_id, COUNT(*) c')->groupBy('document_type_id')->pluck('c', 'document_type_id');
        $types = DocumentType::orderBy('name')->get();
        foreach ($types as $t) {
            $t->archive_count = $typeCounts[$t->id] ?? 0;
        }

        // Контрагенты (досье).
        $counterparties = $scoped()->whereNotNull('counterparty')
            ->selectRaw('counterparty, COUNT(*) c')->groupBy('counterparty')->orderBy('counterparty')->get();

        return compact('directions', 'departments', 'types', 'counterparties');
    }

    /** Свежий запрос по archived_documents, ограниченный уровнем доступа. */
    private function scoped($user, string $access)
    {
        $query = ArchivedDocument::query();

        if ($access === 'own') {
            $query->where('initiator_id', $user->id);
        } elseif ($access === 'department' && $user->department_id) {
            $query->whereIn('department_id', Department::visibleScopeIds($user->department_id));
        }

        return $query;
    }

    private function authorizeAccess(ArchivedDocument $archived): void
    {
        $user   = auth()->user();
        $access = $user->resolveArchiveAccess();

        if ($access === 'none') {
            abort(403, 'Нет доступа к архиву.');
        }
        if ($access === 'own' && $archived->initiator_id !== $user->id) {
            abort(403);
        }
        if ($access === 'department' && $user->department_id
            && ! in_array($archived->department_id, Department::visibleScopeIds($user->department_id), true)) {
            abort(403);
        }
    }
}
