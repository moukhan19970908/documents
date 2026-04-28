<x-app-layout>
    <x-slot name="title">Сотрудники — Vamin.ru</x-slot>

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Сотрудники</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $totalUsers }} активных сотрудников</p>
        </div>
        {{-- Legend --}}
        <div class="flex items-center gap-3 text-[11px] text-gray-500 flex-wrap">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-200 border border-amber-300 inline-block"></span> Организация</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-cyan-100 border border-cyan-300 inline-block"></span> Дирекция</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-white border border-gray-200 inline-block"></span> Отдел</span>
            <span class="flex items-center gap-1.5"><span class="text-[9px] font-bold px-1 rounded bg-blue-100 text-blue-600 leading-none">Б24</span> Из Битрикс24</span>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: 'chart' }" class="space-y-6">
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
            <button @click="tab = 'chart'"
                :class="tab === 'chart' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Схема
            </button>
            <button @click="tab = 'list'"
                :class="tab === 'list' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Список
            </button>
        </div>

        {{-- ── ORG CHART TAB ── --}}
        <div x-show="tab === 'chart'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="bg-[#F5F8FF] rounded-2xl border border-gray-200 shadow-sm p-8 overflow-auto">
                @if($tree->isEmpty())
                    <div class="py-20 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-5.197-3.787M9 20H4v-2a4 4 0 015.197-3.787M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="font-medium text-gray-500">Отделы не найдены</p>
                        <p class="text-sm text-gray-400 mt-1">Синхронизируйте данные из Битрикс24 или добавьте отделы вручную.</p>
                    </div>
                @else
                    <div class="inline-flex flex-row gap-16 justify-center items-start min-w-max mx-auto pb-4">
                        @foreach($tree as $rootDept)
                            <x-org-node :dept="$rootDept" :depth="0" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── LIST TAB ── --}}
        <div x-show="tab === 'list'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @php
                $roleMap    = ['admin' => 'Администратор', 'director' => 'Директор', 'linear' => 'Сотрудник', 'archiver' => 'Архивариус'];
                $roleColors = ['admin' => 'bg-purple-50 text-purple-700', 'director' => 'bg-blue-50 text-blue-700', 'linear' => 'bg-gray-100 text-gray-600', 'archiver' => 'bg-amber-50 text-amber-700'];
                $allUsers   = \App\Models\User::with('department')->where('is_active', true)->orderBy('name')->get();
            @endphp

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Список сотрудников</h2>
                    <span class="text-xs text-gray-400">{{ $totalUsers }} чел.</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold">Сотрудник</th>
                                <th class="px-6 py-3 text-left font-semibold">Отдел</th>
                                <th class="px-6 py-3 text-left font-semibold">Должность</th>
                                <th class="px-6 py-3 text-left font-semibold">Email</th>
                                <th class="px-6 py-3 text-left font-semibold">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($allUsers as $user)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center">
                                                @if($user->avatar)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                                                @else
                                                    <span class="text-xs font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                                @endif
                                            </div>
                                            <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $user->department?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $user->position ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500">{{ $user->email ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                            Активен
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-400">Сотрудники не найдены</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</x-app-layout>
