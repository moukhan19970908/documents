<?php

namespace App\Http\Controllers;

use App\Models\Counterparty;
use App\Services\CheckoService;
use Illuminate\Http\Request;
use RuntimeException;

/** Справочник контрагентов: ввод ИНН подтягивает карточку организации из Checko. */
class CounterpartyController extends Controller
{
    public function __construct(private CheckoService $checko) {}

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $query = Counterparty::query()->orderBy('short_name');
        if ($search = trim((string) $request->get('search'))) {
            $query->where(fn ($q) => $q
                ->where('short_name', 'like', "%{$search}%")
                ->orWhere('inn', 'like', "%{$search}%"));
        }

        $items = $query->paginate(25)->withQueryString();

        return view('counterparties.index', compact('items'));
    }

    /** Новый ИНН — добавляет организацию; уже известный — обновляет её данные. */
    public function store(Request $request)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'inn' => ['required', 'regex:/^\d{10}$/'],
        ], [
            'inn.regex' => 'ИНН организации — 10 цифр.',
        ]);

        return $this->sync($data['inn']);
    }

    public function refresh(Counterparty $counterparty)
    {
        $this->authorizeAccess();

        return $this->sync($counterparty->inn);
    }

    /**
     * Поиск для поля «counterparty» в формах документов — доступен всем, кто создаёт документы,
     * а не только тем, у кого открыт раздел справочника.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Counterparty::where('short_name', 'like', "%{$q}%")
                ->orWhere('inn', 'like', "{$q}%")
                ->orderBy('short_name')
                ->limit(10)
                ->get(['id', 'inn', 'short_name', 'legal_address'])
        );
    }

    /** Добавление из формы документа: организации нет в справочнике — подтягиваем её из Checko. */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'inn' => ['required', 'regex:/^\d{10}$/'],
        ], [
            'inn.regex' => 'ИНН организации — 10 цифр.',
        ]);

        try {
            [$counterparty] = $this->upsertFromChecko($data['inn']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($counterparty->only(['id', 'inn', 'short_name', 'legal_address']));
    }

    private function sync(string $inn)
    {
        try {
            [$counterparty, $isNew] = $this->upsertFromChecko($inn);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $name = $counterparty->short_name ?: $counterparty->inn;

        return redirect()->route('counterparties.index')
            ->with('success', $isNew ? "Добавлено: {$name}" : "Данные обновлены: {$name}");
    }

    /** @return array{0: Counterparty, 1: bool} организация и признак «добавлена впервые» */
    private function upsertFromChecko(string $inn): array
    {
        $fields = $this->checko->company($inn);

        $counterparty = Counterparty::firstOrNew(['inn' => $fields['inn']]);
        $isNew = ! $counterparty->exists;
        $counterparty->fill($fields + ['synced_at' => now()]);
        if ($isNew) {
            $counterparty->created_by = auth()->id();
        }
        $counterparty->save();

        return [$counterparty, $isNew];
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()->canSeeMenu('menu.counterparties'), 403);
    }
}
