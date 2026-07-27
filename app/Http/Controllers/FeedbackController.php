<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Feedback;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $canViewAll = $user->hasMatrixPermission('feedback.view_all');

        $query = Feedback::with('user')->withCount('messages')->latest('id');

        if (! $canViewAll) {
            // Пользователь видит ТОЛЬКО свои обращения.
            $query->where('user_id', $user->id);
        } else {
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($category = $request->get('category')) {
                $query->where('category', $category);
            }
            if ($author = $request->integer('author') ?: null) {
                $query->where('user_id', $author);
            }
            if ($direction = $request->integer('direction') ?: null) {
                $ids = Department::getDescendantIds($direction);
                $query->whereHas('user', fn ($u) => $u->whereIn('department_id', $ids));
            }
        }

        $items = $query->paginate(20)->withQueryString();

        $authors = $canViewAll
            ? User::whereIn('id', Feedback::query()->select('user_id'))->orderBy('name')->get(['id', 'name'])
            : collect();
        $directions = $canViewAll
            ? Department::whereNull('parent_id')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('feedback.index', compact('items', 'canViewAll', 'authors', 'directions'));
    }

    public function create()
    {
        return view('feedback.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Feedback::CATEGORIES))],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string', 'max:5000'],
        ]);

        $feedback = Feedback::create($data + ['user_id' => auth()->id(), 'status' => 'new']);
        $this->audit->log('feedback_created', $feedback);

        return redirect()->route('feedback.show', $feedback)->with('success', 'Обращение отправлено.');
    }

    public function show(Feedback $feedback)
    {
        $this->authorizeView($feedback);
        $feedback->load('user', 'messages.user');

        return view('feedback.show', [
            'feedback'   => $feedback,
            'canRespond' => auth()->user()->hasMatrixPermission('feedback.reply'),
            'isOwner'    => $feedback->user_id === auth()->id(),
        ]);
    }

    public function reply(Request $request, Feedback $feedback)
    {
        $canRespond = auth()->user()->hasMatrixPermission('feedback.reply');
        $isOwner = $feedback->user_id === auth()->id();
        abort_unless($isOwner || $canRespond, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $feedback->messages()->create(['user_id' => auth()->id(), 'body' => $data['body']]);

        // Ответ обработчика переводит обращение в «Отвечено» (если не закрыто).
        if ($canRespond && ! $isOwner && $feedback->status !== 'closed') {
            $feedback->update(['status' => 'answered']);
        }

        $this->audit->log('feedback_replied', $feedback);

        return back()->with('success', 'Сообщение добавлено.');
    }

    public function updateStatus(Request $request, Feedback $feedback)
    {
        abort_unless(auth()->user()->hasMatrixPermission('feedback.reply'), 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Feedback::STATUSES))]]);
        $feedback->update($data);
        $this->audit->log('feedback_status_changed', $feedback);

        return back()->with('success', 'Статус обновлён.');
    }

    private function authorizeView(Feedback $feedback): void
    {
        $user = auth()->user();
        abort_unless($feedback->user_id === $user->id || $user->hasMatrixPermission('feedback.view_all'), 403);
    }
}
