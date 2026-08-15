<x-app-layout>
    <x-slot name="title">{{ $archived->title }} — Архив</x-slot>

    @php
        $m = $archived->metadata ?? [];
        $kind = $m['kind'] ?? null;
        $kindLabel = ['document' => 'Документ', 'order' => 'Приказ', 'assignment' => 'Поручение'][$kind] ?? 'Запись';
    @endphp

    {{-- Хлебные крошки --}}
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="{{ route('archive.index') }}" class="hover:text-gray-700">Архив</a>
        <span>›</span>
        <span class="text-gray-600">{{ $archived->number ?? 'A-' . $archived->id }}</span>
    </div>

    <div class="flex items-start justify-between gap-4 mb-5">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900">{{ $archived->title }}</h1>
                <span class="text-xs uppercase tracking-wide px-2 py-0.5 rounded bg-indigo-50 text-[#5B4FE8]">{{ $kindLabel }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">В архиве</span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $archived->number ?? 'без номера' }}
                @if($m['direction'] ?? null) · {{ $m['direction'] }} @endif
                @if($m['department'] ?? null) · {{ $m['department'] }} @endif
                · в архиве с {{ $archived->archived_at?->format('d.m.Y H:i') }}
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($archived->file_path)
                <a href="{{ route('archive.file', $archived) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Скачать файл</a>
            @endif
            @if($archived->approval_sheet_path)
                <a href="{{ route('archive.sheet', $archived) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Лист согласования</a>
            @endif
            @if($archived->acknowledgment_sheet_path)
                <a href="{{ route('archive.ack-sheet', $archived) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Лист ознакомления</a>
            @endif
            @if($archived->acceptance_sheet_path)
                <a href="{{ route('archive.intake-sheet', $archived) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Лист приёма</a>
            @endif
            @if($openUrl)
                <a href="{{ $openUrl }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Открыть оригинал</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Тело / контент --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                @if($archived->body_html)
                    <div class="prose max-w-none text-sm">{!! $archived->body_html !!}</div>
                @elseif($archived->file_path)
                    <p class="text-sm text-gray-500">Содержимое хранится файлом — <a href="{{ route('archive.file', $archived) }}" class="text-[#5B4FE8] hover:underline">скачать</a>.</p>
                @else
                    <p class="text-sm text-gray-400">Нет тела документа.</p>
                @endif
            </div>

            {{-- Приказ: ознакомления --}}
            @if($kind === 'order' && !empty($m['acknowledgments']))
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="font-semibold text-gray-900 mb-3">Ознакомление ({{ collect($m['acknowledgments'])->whereNotNull('acknowledged_at')->count() }} из {{ count($m['acknowledgments']) }})</h2>
                    <div class="divide-y divide-gray-50">
                        @foreach($m['acknowledgments'] as $ack)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-gray-800">{{ $ack['user'] ?? '—' }}</span>
                                <span class="text-xs {{ ($ack['acknowledged_at'] ?? null) ? 'text-emerald-600' : 'text-gray-400' }}">
                                    {{ ($ack['acknowledged_at'] ?? null) ? \Illuminate\Support\Carbon::parse($ack['acknowledged_at'])->format('d.m.Y H:i') : 'не ознакомлен' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Поручение: подпоручения --}}
            @if($kind === 'assignment' && !empty($m['sub_assignments']))
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="font-semibold text-gray-900 mb-3">Подпоручения ({{ count($m['sub_assignments']) }})</h2>
                    <div class="divide-y divide-gray-50">
                        @foreach($m['sub_assignments'] as $sub)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-gray-800 truncate">{{ $sub['title'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $sub['executor'] ?? '' }} · {{ $sub['status'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Поручение: файлы дела --}}
            @if($kind === 'assignment' && !empty($m['files']))
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="font-semibold text-gray-900 mb-3">Файлы дела ({{ count($m['files']) }})</h2>
                    <ul class="space-y-1.5 text-sm">
                        @foreach($m['files'] as $f)
                            <li class="flex items-center gap-2 text-gray-700">
                                <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ $f['name'] ?? 'файл' }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-gray-400 mt-2">Копии файлов сохранены в неизменяемом виде вместе с делом.</p>
                </div>
            @endif
        </div>

        {{-- Метаданные --}}
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Метаданные</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Инициатор</dt><dd class="text-gray-900 text-right">{{ $m['initiator'] ?? ($archived->initiator?->name ?? '—') }}</dd></div>
                    @if($m['type'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Тип</dt><dd class="text-gray-900 text-right">{{ $m['type'] }}</dd></div>@endif
                    @if($m['subtype'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Подтип</dt><dd class="text-gray-900 text-right">{{ $m['subtype'] }}</dd></div>@endif
                    @if($m['order_kind'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Вид приказа</dt><dd class="text-gray-900 text-right">{{ $m['order_kind'] }}</dd></div>@endif
                    @if($archived->counterparty)<div class="flex justify-between gap-3"><dt class="text-gray-500">Контрагент</dt><dd class="text-gray-900 text-right">{{ $archived->counterparty }}</dd></div>@endif
                    @if($m['executor'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Исполнитель</dt><dd class="text-gray-900 text-right">{{ $m['executor'] }}</dd></div>@endif
                    @if($m['controller'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Контролёр</dt><dd class="text-gray-900 text-right">{{ $m['controller'] }}</dd></div>@endif
                    @if($m['published_at'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Опубликован</dt><dd class="text-gray-900 text-right">{{ \Illuminate\Support\Carbon::parse($m['published_at'])->format('d.m.Y') }}</dd></div>@endif
                    @if($m['registered_at'] ?? null)<div class="flex justify-between gap-3"><dt class="text-gray-500">Зарегистрирован</dt><dd class="text-gray-900 text-right">{{ $m['registered_at'] }}</dd></div>@endif
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">В архиве</dt><dd class="text-gray-900 text-right">{{ $archived->archived_at?->format('d.m.Y H:i') }}</dd></div>
                </dl>
            </div>

            {{-- Участники / согласующие --}}
            @if(!empty($m['participants']))
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Участники согласования</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($m['participants'] as $p)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $p }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(!empty($m['approvers']))
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Согласующие</p>
                    <div class="space-y-1.5 text-sm">
                        @foreach($m['approvers'] as $ap)
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-800">{{ $ap['user'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $ap['role'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($archived->content_hash)
                <p class="text-[11px] text-gray-400 break-all px-1">SHA-256: {{ $archived->content_hash }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
