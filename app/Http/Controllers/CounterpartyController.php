<?php

namespace App\Http\Controllers;

use App\Models\Counterparty;
use App\Services\CheckoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Справочник контрагентов: ввод ИНН организации или ИП подтягивает карточку из Checko,
 * а иностранную компанию (её Checko не знает) вносят вручную.
 */
class CounterpartyController extends Controller
{
    /** ИНН организации — 10 цифр, индивидуального предпринимателя — 12. */
    private const INN_RULE = 'regex:/^(\d{10}|\d{12})$/';

    private const INN_MESSAGE = 'ИНН — 10 цифр у организации или 12 у ИП.';

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

        // ?edit=id — открыть ручную карточку на правку в той же форме, что и добавление.
        $editing = $request->filled('edit') ? Counterparty::whereNull('synced_at')->find($request->integer('edit')) : null;

        return view('counterparties.index', compact('items', 'editing'));
    }

    /** Новый ИНН — добавляет контрагента; уже известный — обновляет его данные. */
    public function store(Request $request)
    {
        $this->authorizeAccess();

        $data = $request->validate(['inn' => ['required', self::INN_RULE]], ['inn.regex' => self::INN_MESSAGE]);

        return $this->sync($data['inn']);
    }

    public function refresh(Counterparty $counterparty)
    {
        $this->authorizeAccess();
        abort_if($counterparty->inn === null, 422, 'У контрагента нет ИНН — обновлять из Checko нечего.');

        return $this->sync($counterparty->inn);
    }

    /**
     * Ручное добавление — для тех, кого Checko не найдёт (иностранные компании). Доступно и из
     * справочника, и из поля «Контрагент» формы документа, поэтому, как и quickStore, — всем,
     * кто создаёт документы. Из формы документа приходит JSON-запрос, ответ — тоже JSON.
     */
    public function storeManual(Request $request)
    {
        $data = $request->validate($this->manualRules(), $this->manualMessages());

        // synced_at остаётся пустым — так видно, что карточка внесена вручную, а не из Checko.
        $counterparty = Counterparty::create($this->manualAttributes($data) + ['created_by' => auth()->id()]);

        if ($request->expectsJson()) {
            return response()->json($counterparty->only(['id', 'inn', 'short_name', 'legal_address']));
        }

        return redirect()->route('counterparties.index')->with('success', "Добавлено вручную: {$counterparty->short_name}");
    }

    /**
     * Правка ручной карточки (опечатка в названии и т.п.). Карточку из Checko не правят:
     * её данные всё равно перезапишет «Обновить».
     */
    public function update(Request $request, Counterparty $counterparty)
    {
        $this->authorizeAccess();
        abort_if($counterparty->synced_at !== null, 422, 'Карточку из Checko обновляет кнопка «Обновить», а не ручная правка.');

        $data = $request->validate($this->manualRules($counterparty), $this->manualMessages());
        $counterparty->update($this->manualAttributes($data));

        return redirect()->route('counterparties.index')->with('success', "Сохранено: {$counterparty->short_name}");
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

    /** Добавление из формы документа: контрагента нет в справочнике — подтягиваем его из Checko. */
    public function quickStore(Request $request)
    {
        $data = $request->validate(['inn' => ['required', self::INN_RULE]], ['inn.regex' => self::INN_MESSAGE]);

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

    /** @return array{0: Counterparty, 1: bool} контрагент и признак «добавлен впервые» */
    private function upsertFromChecko(string $inn): array
    {
        $fields = $this->checko->findByInn($inn);

        $counterparty = Counterparty::firstOrNew(['inn' => $fields['inn']]);
        $isNew = ! $counterparty->exists;
        $counterparty->fill($fields + ['synced_at' => now()]);
        if ($isNew) {
            $counterparty->created_by = auth()->id();
        }
        $counterparty->save();

        return [$counterparty, $isNew];
    }

    /** Поля ручной карточки; при правке её собственный ИНН дублем не считается. */
    private function manualRules(?Counterparty $editing = null): array
    {
        return [
            'short_name'    => ['required', 'string', 'max:255'],
            'inn'           => ['nullable', self::INN_RULE, Rule::unique('counterparties', 'inn')->ignore($editing)],
            'legal_address' => ['nullable', 'string', 'max:1000'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'email'         => ['nullable', 'email', 'max:255'],
            'website'       => ['nullable', 'string', 'max:255'],
        ];
    }

    private function manualMessages(): array
    {
        return [
            'inn.regex'  => self::INN_MESSAGE,
            'inn.unique' => 'Контрагент с таким ИНН уже есть в справочнике.',
        ];
    }

    private function manualAttributes(array $data): array
    {
        return [
            'short_name'    => $data['short_name'],
            'inn'           => $data['inn'] ?? null,
            'legal_address' => $data['legal_address'] ?? null,
            'director_name' => $data['director_name'] ?? null,
            'phones'        => array_values(array_filter([$data['phone'] ?? null])),
            'emails'        => array_values(array_filter([$data['email'] ?? null])),
            'website'       => $data['website'] ?? null,
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()->canSeeMenu('menu.counterparties'), 403);
    }
}
