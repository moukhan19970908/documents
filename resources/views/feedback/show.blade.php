<x-app-layout>
    <x-slot name="title">{{ $feedback->subject }} — Обратная связь</x-slot>

    @php
        $statusClasses = ['blue' => 'bg-blue-50 text-blue-600', 'amber' => 'bg-amber-50 text-amber-600', 'violet' => 'bg-violet-50 text-violet-600', 'gray' => 'bg-gray-100 text-gray-600'];
        $catClasses = ['red' => 'bg-red-50 text-red-600', 'emerald' => 'bg-emerald-50 text-emerald-600', 'blue' => 'bg-blue-50 text-blue-600', 'gray' => 'bg-gray-100 text-gray-600'];
    @endphp

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="{{ route('feedback.index') }}" class="hover:text-gray-700">Обратная связь</a>
        <span>›</span>
        <span class="text-gray-600">Обращение №{{ $feedback->id }}</span>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            {{-- Шапка --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $catClasses[$feedback->categoryColor()] }}">{{ $feedback->categoryLabel() }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $statusClasses[$feedback->statusColor()] }}">{{ $feedback->statusLabel() }}</span>
                </div>
                <h1 class="text-xl font-bold text-gray-900">{{ $feedback->subject }}</h1>
                <div class="flex items-center gap-2 mt-3">
                    <img src="{{ $feedback->user->avatar_url }}" alt="" class="w-6 h-6 rounded-full">
                    <span class="text-sm text-gray-600">{{ $feedback->user->name }}</span>
                    <span class="text-xs text-gray-400">· {{ $feedback->created_at->translatedFormat('j M Y, H:i') }}</span>
                </div>
                <div class="prose max-w-none text-sm text-gray-700 mt-4 whitespace-pre-line">{{ $feedback->body }}</div>
            </div>

            {{-- Тред --}}
            @foreach($feedback->messages as $msg)
                @php $mine = $msg->user_id === $feedback->user_id; @endphp
                <div class="bg-white border rounded-xl p-5 {{ $mine ? 'border-gray-200' : 'border-[#5B4FE8]/30 bg-[#5B4FE8]/[0.02]' }}">
                    <div class="flex items-center gap-2 mb-2">
                        <img src="{{ $msg->user->avatar_url }}" alt="" class="w-6 h-6 rounded-full">
                        <span class="text-sm font-medium text-gray-800">{{ $msg->user->name }}</span>
                        @unless($mine)<span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-[#5B4FE8]/10 text-[#5B4FE8]">поддержка</span>@endunless
                        <span class="text-xs text-gray-400">· {{ $msg->created_at->translatedFormat('j M, H:i') }}</span>
                    </div>
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ $msg->body }}</div>
                </div>
            @endforeach

            {{-- Ответ --}}
            @if(($canRespond || $isOwner) && $feedback->status !== 'closed')
                <form method="POST" action="{{ route('feedback.reply', $feedback) }}" class="bg-white border border-gray-200 rounded-xl p-5">
                    @csrf
                    <textarea name="body" rows="3" required maxlength="5000"
                              placeholder="{{ $canRespond && ! $isOwner ? 'Ответить пользователю…' : 'Дополнить обращение…' }}"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                    <div class="flex justify-end mt-3">
                        <button type="submit" class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Отправить</button>
                    </div>
                </form>
            @elseif($feedback->status === 'closed')
                <div class="bg-gray-50 border border-gray-200 rounded-xl px-5 py-4 text-sm text-gray-500 text-center">Обращение закрыто.</div>
            @endif
        </div>

        {{-- Управление (обработчик) --}}
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Данные</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Автор</dt><dd class="text-gray-900 text-right">{{ $feedback->user->name }}</dd></div>
                    @if($feedback->user->department)<div class="flex justify-between gap-3"><dt class="text-gray-500">Отдел</dt><dd class="text-gray-900 text-right">{{ $feedback->user->department->name }}</dd></div>@endif
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Категория</dt><dd class="text-gray-900 text-right">{{ $feedback->categoryLabel() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Создано</dt><dd class="text-gray-900 text-right">{{ $feedback->created_at->format('d.m.Y') }}</dd></div>
                </dl>
            </div>

            @if($canRespond)
                <form method="POST" action="{{ route('feedback.status', $feedback) }}" class="bg-white border border-gray-200 rounded-xl p-5">
                    @csrf @method('PATCH')
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-widest block mb-2">Статус обращения</label>
                    <select name="status" onchange="this.form.submit()" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @foreach(\App\Models\Feedback::STATUSES as $k => $v)
                            <option value="{{ $k }}" @selected($feedback->status===$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-2">Новое → В работе → Отвечено → Закрыто</p>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
