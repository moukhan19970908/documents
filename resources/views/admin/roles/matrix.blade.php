<x-app-layout>
    <x-slot name="title">Матрица прав — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.matrix.update') }}"
          x-data="{
              open: @js(collect($groups)->mapWithKeys(fn ($g) => [$g['key'] => true])),
              roles: @js($roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()),
              roleId: @js($roles->first()?->id),
              picker: false,
              q: '',
              get all() { return this.roleId === null },
              get label() { return this.roles.find(r => r.id === this.roleId)?.name ?? 'Все роли' },
              get found() {
                  const q = this.q.trim().toLowerCase();
                  return q ? this.roles.filter(r => r.name.toLowerCase().includes(q)) : this.roles;
              },
              pick(id) { this.roleId = id; this.picker = false; this.q = ''; },
          }">
        @csrf
        @method('PUT')

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Роли показываем по одной: полная матрица не помещается на экран --}}
                <div class="relative" @click.outside="picker = false" @keydown.escape="picker = false">
                    <button type="button"
                            @click="picker = !picker; if (picker) $nextTick(() => $refs.roleSearch.focus())"
                            class="flex items-center gap-2 w-64 px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 hover:border-[#5B4FE8] focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <span class="truncate" x-text="label"></span>
                        <svg class="w-4 h-4 text-gray-400 ml-auto shrink-0" xmlns="http://www.w3.org/2000/svg"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="picker" x-transition style="display:none"
                         class="absolute left-0 top-full mt-1 z-40 w-64 bg-white border border-gray-200 rounded-lg shadow-lg">
                        <div class="p-2 border-b border-gray-100">
                            <input x-ref="roleSearch" x-model="q" type="text" placeholder="Поиск роли…"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                        <div class="max-h-64 overflow-y-auto py-1">
                            <button type="button" x-show="q.trim() === ''" @click="pick(null)"
                                    class="w-full px-3 py-1.5 text-left text-sm hover:bg-gray-50"
                                    :class="all ? 'text-[#5B4FE8] font-medium' : 'text-gray-500'">
                                Все роли
                            </button>
                            <template x-for="role in found" :key="role.id">
                                <button type="button" @click="pick(role.id)"
                                        class="w-full px-3 py-1.5 text-left text-sm truncate hover:bg-gray-50"
                                        :class="role.id === roleId ? 'text-[#5B4FE8] font-medium' : 'text-gray-700'"
                                        x-text="role.name"></button>
                            </template>
                            <p x-show="found.length === 0" class="px-3 py-3 text-sm text-gray-400 text-center">Роль не найдена</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded border-2 border-amber-400 bg-amber-50 shrink-0"></span>
                    <span class="text-xs text-gray-500">узкое право — используйте выборочно</span>
                </div>
            </div>

            <button type="submit"
                    class="px-4 py-2 rounded-lg bg-[#5B4FE8] text-white text-sm font-medium hover:bg-[#4a3fd0] transition-colors">
                Сохранить
            </button>
        </div>

        <div x-cloak class="bg-white rounded-xl border border-gray-200 overflow-auto max-h-[calc(100vh-16rem)]">
            <table class="w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 left-0 z-30 bg-gray-50 border-b border-gray-200 p-0 align-bottom w-72 min-w-72"></th>
                        @foreach($roles as $role)
                            <th x-show="all || roleId === {{ $role->id }}"
                                class="sticky top-0 z-20 bg-gray-50 border-b border-gray-200 p-0 align-bottom"
                                :class="all ? 'h-36 w-12 min-w-12' : 'h-11 px-4'">
                                <div class="relative" :class="all ? 'h-36 w-12' : 'flex items-center justify-center h-11'">
                                    <span class="text-xs font-medium text-gray-500"
                                          :class="all ? 'absolute bottom-2 left-1/2 origin-bottom-left -rotate-45 whitespace-nowrap' : ''"
                                          title="{{ $role->name }}">{{ $role->name }}</span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($groups as $group)
                        <tr class="bg-gray-50/70">
                            <td colspan="{{ $roles->count() + 1 }}" class="border-b border-gray-200 p-0">
                                <button type="button" @click="open[@js($group['key'])] = !open[@js($group['key'])]"
                                        class="sticky left-0 flex items-center gap-2 w-72 px-5 py-2.5 text-left">
                                    <svg :class="open[@js($group['key'])] ? 'rotate-90' : ''"
                                         class="w-3.5 h-3.5 text-gray-400 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest">{{ $group['label'] }}</span>
                                    <span class="text-xs text-gray-400">({{ count($group['items']) }})</span>
                                </button>
                            </td>
                        </tr>

                        @foreach($group['items'] as $item)
                            @php $narrow = $item['narrow'] ?? false; @endphp
                            <tr x-show="open[@js($group['key'])]" class="{{ $narrow ? 'bg-amber-50/40' : 'hover:bg-gray-50/50' }}">
                                <td class="sticky left-0 z-10 border-b border-gray-100 px-5 py-2.5
                                           {{ $narrow ? 'bg-[#FEFCF3]' : 'bg-white' }}
                                           {{ ($item['depth'] ?? 0) > 0 ? 'pl-11' : '' }}">
                                    <span class="text-sm {{ ($item['depth'] ?? 0) > 0 ? 'text-gray-600' : 'font-semibold text-gray-900' }}">
                                        {{ $item['label'] }}
                                    </span>
                                    @if($narrow)
                                        <svg class="inline-block w-3.5 h-3.5 text-amber-500 ml-1 -mt-0.5" xmlns="http://www.w3.org/2000/svg"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                             title="Узкое право — используйте выборочно">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                                        </svg>
                                    @endif
                                </td>

                                @foreach($roles as $role)
                                    @php
                                        // Состояние берём из БД (role_permissions), см. RoleController::matrix.
                                        $allowed = in_array($role->id, $granted[$item['key']] ?? []);
                                    @endphp
                                    {{-- Скрытые роли остаются в DOM: форма сохраняет матрицу целиком. --}}
                                    <td x-show="all || roleId === {{ $role->id }}" class="border-b border-gray-100 text-center">
                                        <input type="checkbox"
                                               name="permissions[{{ $item['key'] }}][]" value="{{ $role->id }}"
                                               @checked($allowed)
                                               class="w-5 h-5 rounded cursor-pointer
                                                      {{ ($item['narrow'] ?? false)
                                                         ? 'accent-amber-500 border-amber-300'
                                                         : 'accent-[#5B4FE8] border-gray-300' }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400 mt-3">
            Раздел «Видимость меню» управляет пунктами сайдбара для ролей. Остальные разделы сохраняются, но применение — в разработке.
            Выбор роли влияет только на отображение: при сохранении права всех ролей записываются целиком.
        </p>
    </form>
</x-app-layout>
