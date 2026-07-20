<?php

namespace App\Http\Controllers;

use App\Models\BlankTemplate;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Bitrix24Service;
use App\Services\NotificationService;
use App\Services\OrderAudienceService;
use App\Services\OrderNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

class OrderController extends Controller
{
    public function __construct(
        private OrderNumberService $numberService,
        private OrderAudienceService $audienceService,
        private NotificationService $notifications,
        private AuditService $audit,
        private Bitrix24Service $bitrix,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        // Перспектива: издающий (свои/все приказы) или сотрудник (адресованные мне).
        $perspective = $request->get('as', $user->canIssueOrders() ? 'issuer' : 'employee');
        $tab = $request->get('tab', 'all');

        $query = Order::with('initiator')->withCount([
            'acknowledgments as recipients_total',
            'acknowledgments as acknowledged_total' => fn ($q) => $q->whereNotNull('acknowledged_at'),
        ])->latest('id');

        if ($tab === 'ack') {
            // Требуют моего ознакомления — только неознакомленные, где я адресат.
            $query->where('status', 'published')
                ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $user->id)->whereNull('acknowledged_at'));
        } elseif ($perspective === 'employee') {
            $query->where('status', 'published')
                ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $user->id));
        } else {
            // Издающий: свои приказы; с правом orders.view_all — все.
            if (! $user->hasMatrixPermission('orders.view_all')) {
                $query->where('initiator_id', $user->id);
            }
        }

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%"));
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($initiator = $request->get('initiator')) {
            $query->where('initiator_id', $initiator);
        }
        if ($dept = $request->get('department')) {
            $query->whereJsonContains('audience->department_ids', (int) $dept);
        }
        if ($from = $request->get('date_from')) {
            $query->whereDate('published_at', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('published_at', '<=', $to);
        }

        $orders = $query->paginate(20)->withQueryString();

        // Количество «требуют моего ознакомления» для бейджа вкладки.
        $ackPending = Order::where('status', 'published')
            ->whereHas('acknowledgments', fn ($q) => $q->where('user_id', $user->id)->whereNull('acknowledged_at'))
            ->count();

        $initiators = User::whereIn('id', Order::query()->select('initiator_id'))->orderBy('name')->get(['id', 'name']);
        $directions = Department::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('orders.index', compact('orders', 'perspective', 'tab', 'ackPending', 'initiators', 'directions'));
    }

    public function create()
    {
        abort_unless(auth()->user()->canIssueOrders(), 403, 'Нет права издания приказов.');

        return view('orders.create', $this->formData());
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canIssueOrders(), 403);

        $data = $this->validateOrder($request);
        $order = new Order();
        $this->fillFromRequest($order, $request, $data);
        $order->initiator_id = auth()->id();
        $order->status = 'draft';
        $order->save();

        $this->audit->log('order_created', $order);

        if ($request->input('action') === 'publish') {
            return $this->publishOrApprove($order, $request);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Приказ сохранён черновиком.');
    }

    public function edit(Order $order)
    {
        abort_unless($order->isDraft() && $order->initiator_id === auth()->id(), 403);

        return view('orders.create', array_merge($this->formData(), ['order' => $order]));
    }

    public function update(Request $request, Order $order)
    {
        abort_unless($order->isDraft() && $order->initiator_id === auth()->id(), 403);

        $data = $this->validateOrder($request);
        $this->fillFromRequest($order, $request, $data);
        $order->save();

        $this->audit->log('order_updated', $order);

        if ($request->input('action') === 'publish') {
            return $this->publishOrApprove($order, $request);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Черновик обновлён.');
    }

    public function publish(Request $request, Order $order)
    {
        abort_unless($order->isDraft() && $order->initiator_id === auth()->id(), 403);

        return $this->publishOrApprove($order, $request);
    }

    public function approve(Order $order)
    {
        $approval = $order->approvals()->where('approver_id', auth()->id())->where('status', 'pending')->first();
        abort_unless($approval && $order->status === 'on_approval', 403);

        $approval->update(['status' => 'approved', 'decided_at' => now()]);
        $this->closeOrderTasks($order, auth()->id(), 'completed');
        $this->audit->log('order_approval_approved', $order);

        // Все согласующие одобрили — публикуем.
        if ($order->approvals()->where('status', '!=', 'approved')->doesntExist()) {
            return $this->finalizePublish($order->fresh());
        }

        return back()->with('success', 'Вы согласовали приказ.');
    }

    public function reject(Request $request, Order $order)
    {
        $approval = $order->approvals()->where('approver_id', auth()->id())->where('status', 'pending')->first();
        abort_unless($approval && $order->status === 'on_approval', 403);

        $approval->update([
            'status'     => 'rejected',
            'comment'    => $request->input('comment'),
            'decided_at' => now(),
        ]);
        $order->update(['status' => 'draft']);
        $this->closeOrderTasks($order, null, 'cancelled');
        $this->audit->log('order_approval_rejected', $order);

        $this->notifications->notify($order->initiator, 'order_rejected', [
            'title' => $order->title, 'order_id' => $order->id,
        ]);

        return back()->with('success', 'Приказ отклонён и возвращён инициатору.');
    }

    public function show(Order $order)
    {
        $order->load(['initiator.department', 'blank', 'approvals.approver']);

        $acks = $order->acknowledgments()
            ->with('user.department')
            ->get()
            ->sortBy(fn ($a) => $a->user->name);

        $acknowledged = $acks->whereNotNull('acknowledged_at')->values();
        $pending      = $acks->whereNull('acknowledged_at')->values();

        // Отделы адресатов для фильтра.
        $deptFilter = $acks->pluck('user.department')->filter()->unique('id')->sortBy('name')->values();

        return view('orders.show', compact('order', 'acknowledged', 'pending', 'deptFilter'));
    }

    public function acknowledge(Order $order)
    {
        $ack = $order->acknowledgments()->where('user_id', auth()->id())->first();
        abort_unless($ack, 403, 'Вы не адресат этого приказа.');

        if (! $ack->acknowledged_at) {
            $ack->update(['acknowledged_at' => now()]);
            $this->closeOrderTasks($order, auth()->id(), 'completed');
            $this->audit->log('order_acknowledged', $order);
        }

        return back()->with('success', 'Вы ознакомились с приказом.');
    }

    public function remind(Order $order)
    {
        abort_unless($order->initiator_id === auth()->id() || auth()->user()->canIssueOrders(), 403);

        $pending = $order->acknowledgments()->whereNull('acknowledged_at')->with('user')->get();

        foreach ($pending as $ack) {
            $this->notifications->notify($ack->user, 'order_ack_reminder', [
                'title'    => $order->title,
                'order_id' => $order->id,
            ]);
            $ack->update(['reminded_at' => now()]);
        }

        return back()->with('success', "Напоминание отправлено: {$pending->count()} чел.");
    }

    public function file(Order $order)
    {
        abort_unless($order->file_path && Storage::exists($order->file_path), 404);

        return Storage::download($order->file_path, $order->file_name);
    }

    public function pdf(Order $order)
    {
        $order->load('initiator');

        // Если установлен DomPDF — отдаём настоящий PDF, иначе печатная HTML-версия.
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.order', compact('order'))
                ->download(($order->number ?? 'order') . '.pdf');
        }

        return view('pdf.order', compact('order'));
    }

    public function destroy(Order $order)
    {
        abort_unless($order->isDraft() && $order->initiator_id === auth()->id(), 403);

        $this->audit->log('order_deleted', $order);
        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Черновик удалён.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Метки ролей согласующих (ТЗ 16.1). */
    private const APPROVER_ROLES = ['legal' => 'Юрист', 'hr' => 'Кадры', 'finance' => 'Финансы'];

    /** Поставить задачу по приказу (в «Мои задачи» + Битрикс). Постановщик — инициатор приказа. */
    private function createOrderTask(Order $order, User $assignee, string $title, string $description, $deadline = null): void
    {
        $task = Task::create([
            'order_id'    => $order->id,
            'assignee_id' => $assignee->id,
            'title'       => $title,
            'description' => $description,
            'status'      => 'pending',
            'deadline_at' => $deadline,
        ]);

        $bitrixId = $this->bitrix->createTask($assignee, $title, $description, $deadline);
        if ($bitrixId) {
            $task->update(['bitrix24_task_id' => $bitrixId]);
        }
    }

    /** Закрыть незавершённые задачи приказа (одного адресата или всех) со статусом completed|cancelled. */
    private function closeOrderTasks(Order $order, ?int $userId, string $status): void
    {
        $query = Task::where('order_id', $order->id)->where('status', 'pending');
        if ($userId) {
            $query->where('assignee_id', $userId);
        }

        foreach ($query->get() as $task) {
            if ($task->bitrix24_task_id) {
                $this->bitrix->completeTask($task->bitrix24_task_id);
            }
            $task->update(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null]);
        }
    }

    /** Публикация: либо сразу, либо через фазу согласования. */
    private function publishOrApprove(Order $order, Request $request)
    {
        $approvers = collect($request->input('approvers', []))
            ->only(array_keys(self::APPROVER_ROLES))
            ->filter();

        if ($request->boolean('requires_approval') && $approvers->isNotEmpty()) {
            return $this->sendToApproval($order, $approvers);
        }

        return $this->finalizePublish($order);
    }

    /** Отправить приказ на согласование (юрист / кадры / финансы). */
    private function sendToApproval(Order $order, $approvers)
    {
        $this->assertHasAudience($order);

        DB::transaction(function () use ($order, $approvers) {
            $order->approvals()->delete();
            $pos = 0;
            foreach ($approvers as $role => $userId) {
                $order->approvals()->create([
                    'approver_id' => (int) $userId,
                    'role_label'  => self::APPROVER_ROLES[$role] ?? $role,
                    'position'    => $pos++,
                    'status'      => 'pending',
                ]);
            }
            $order->update(['status' => 'on_approval', 'requires_approval' => true]);
        });

        $url = route('orders.show', $order->id);
        foreach ($order->approvals()->with('approver')->get() as $a) {
            $this->notifications->notify($a->approver, 'order_approval', [
                'title' => $order->title, 'order_id' => $order->id,
            ]);
            // Задача согласующему: проверить приказ перед публикацией (ТЗ приказы, п.1).
            $this->createOrderTask(
                $order,
                $a->approver,
                "Проверьте приказ - {$order->title} перед публикацией",
                "Поступил новый приказ на проверку перед публикацией - {$order->title}\n\nСсылка на документ — {$url}",
            );
        }

        $this->audit->log('order_sent_to_approval', $order);

        return redirect()->route('orders.show', $order)->with('success', 'Приказ отправлен на согласование.');
    }

    /** Раскрыть аудиторию, присвоить номер, зафиксировать снапшот ознакомления. */
    private function finalizePublish(Order $order)
    {
        $userIds = $this->assertHasAudience($order);

        DB::transaction(function () use ($order, $userIds) {
            $this->numberService->assign($order);

            $rows = array_map(fn ($uid) => [
                'order_id' => $order->id, 'user_id' => $uid,
                'created_at' => now(), 'updated_at' => now(),
            ], $userIds);
            $order->acknowledgments()->getModel()->insertOrIgnore($rows);

            $order->update([
                'status'       => 'published',
                'published_at' => now(),
                'effective_at' => $order->effective_at ?? now(),
            ]);
        });

        $ackUsers = User::whereIn('id', $userIds)->get();

        $this->notifications->notifyMany(
            $ackUsers,
            'order_published',
            ['title' => $order->title, 'order_id' => $order->id],
        );

        // Задача каждому адресату на ознакомление (ТЗ приказы, п.2) — своя задача, чтобы все увидели.
        $url = route('orders.show', $order->id);
        foreach ($ackUsers as $u) {
            $this->createOrderTask(
                $order,
                $u,
                "Ознакомьтесь с новым приказом - {$order->title}",
                "Поступил новый приказ - {$order->title}\nТребуется ваше ознакомление!\n\nСсылка на документ — {$url}",
                $order->ack_deadline,
            );
        }

        $this->audit->log('order_published', $order);

        return redirect()->route('orders.show', $order)
            ->with('success', "Приказ {$order->number} опубликован. Адресатов: " . count($userIds) . '.');
    }

    /** @return array<int, int> resolved recipient ids */
    private function assertHasAudience(Order $order): array
    {
        $audience = $order->audience ?? [];
        $userIds = $this->audienceService->resolveUserIds(
            $audience['department_ids'] ?? [],
            $audience['user_ids'] ?? [],
        );

        if (empty($userIds)) {
            throw ValidationException::withMessages(['audience' => 'Укажите хотя бы одного адресата.']);
        }

        return $userIds;
    }

    private function fillFromRequest(Order $order, Request $request, array $data): void
    {
        $order->fill([
            'kind'         => $data['kind'],
            'title'        => $data['title'],
            'effective_at' => $data['effective_at'] ?? null,
            'ack_deadline' => $data['ack_deadline'] ?? null,
            'requires_approval' => $request->boolean('requires_approval'),
            'audience'     => $this->buildAudience($data),
        ]);

        if (($data['source'] ?? 'blank') === 'file' && $request->hasFile('file')) {
            $file = $request->file('file');
            $order->file_path = $file->store('orders');
            $order->file_name = $file->getClientOriginalName();
            $order->blank_template_id = null;
            $order->body_html = null;
        } else {
            $order->blank_template_id = $data['blank_template_id'] ?? null;
            $order->body_html = isset($data['body_html']) ? Purifier::clean($data['body_html'], 'blank') : null;
        }
    }

    /** Снапшот выбора для показа: сырые id + подписи/счётчики. */
    private function buildAudience(array $data): array
    {
        $deptIds = array_map('intval', $data['department_ids'] ?? []);
        $userIds = array_map('intval', $data['user_ids'] ?? []);

        $counts = $this->audienceService->departmentCounts();
        $departments = Department::whereIn('id', $deptIds)->orderBy('name')->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'count' => $counts[$d->id] ?? 0])->all();
        $users = User::whereIn('id', $userIds)->orderBy('name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        return compact('deptIds', 'userIds', 'departments', 'users') + [
            'department_ids' => $deptIds,
            'user_ids'       => $userIds,
        ];
    }

    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'kind'              => ['required', Rule::in(array_keys(Order::KINDS))],
            'title'             => ['required', 'string', 'max:255'],
            'effective_at'      => ['nullable', 'date'],
            'ack_deadline'      => ['nullable', 'date'],
            'source'            => ['nullable', Rule::in(['blank', 'file'])],
            'blank_template_id' => ['nullable', 'exists:blank_templates,id'],
            'body_html'         => ['nullable', 'string'],
            'file'              => ['nullable', 'file', 'max:51200'],
            'department_ids'    => ['array'],
            'department_ids.*'  => ['integer', 'exists:departments,id'],
            'user_ids'          => ['array'],
            'user_ids.*'        => ['integer', 'exists:users,id'],
            'requires_approval' => ['nullable', 'boolean'],
            'approvers'         => ['array'],
            'approvers.*'       => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function formData(): array
    {
        $type = DocumentType::where('slug', 'order')->first();
        $blanks = $type
            ? BlankTemplate::where('document_type_id', $type->id)->where('is_active', true)->get(['id', 'name', 'content'])
            : collect();

        $members = $this->audienceService->departmentUserIds();
        $tree = $this->departmentTree(null, $members);

        $people = User::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'department_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'position' => $u->position])
            ->values();

        return [
            'kinds'  => Order::KINDS,
            'blanks' => $blanks,
            'tree'   => $tree,
            'people' => $people,
        ];
    }

    /** Дерево отделов с составом сотрудников поддерева (для чекбоксов адресатов). */
    private function departmentTree(?int $parentId, array $members): array
    {
        return Department::where('parent_id', $parentId)->orderBy('name')->get()
            ->map(fn ($d) => [
                'id'       => $d->id,
                'name'     => $d->name,
                'count'    => count($members[$d->id] ?? []),
                'user_ids' => $members[$d->id] ?? [],
                'children' => $this->departmentTree($d->id, $members),
            ])->values()->all();
    }
}
