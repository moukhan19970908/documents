<x-app-layout>
    <x-slot name="title">Матрица прав — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    <div x-data="{ open: @js(collect($groups)->mapWithKeys(fn ($g) => [$g['key'] => true])) }">

        <div class="flex items-center gap-2 mb-4">
            <span class="w-4 h-4 rounded border-2 border-amber-400 bg-amber-50 shrink-0"></span>
            <span class="text-xs text-gray-500">узкое право — используйте выборочно</span>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-auto max-h-[calc(100vh-16rem)]">
            <table class="w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 left-0 z-30 bg-gray-50 border-b border-gray-200 p-0 align-bottom w-72 min-w-72"></th>
                        @foreach($roles as $role)
                            <th class="sticky top-0 z-20 bg-gray-50 border-b border-gray-200 p-0 align-bottom h-36 w-12 min-w-12">
                                <div class="relative h-36 w-12">
                                    <span class="absolute bottom-2 left-1/2 origin-bottom-left -rotate-45 whitespace-nowrap text-xs font-medium text-gray-500"
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
                            <tr x-show="open[@js($group['key'])]" class="hover:bg-gray-50/50">
                                <td class="sticky left-0 z-10 bg-white border-b border-gray-100 px-5 py-2.5
                                           {{ ($item['depth'] ?? 0) > 0 ? 'pl-11' : '' }}">
                                    <span class="text-sm {{ ($item['depth'] ?? 0) > 0 ? 'text-gray-600' : 'font-semibold text-gray-900' }}">
                                        {{ $item['label'] }}
                                    </span>
                                </td>

                                @foreach($roles as $role)
                                    <td class="border-b border-gray-100 text-center">
                                        <input type="checkbox"
                                               name="permissions[{{ $item['key'] }}][]" value="{{ $role->id }}"
                                               @checked(!in_array($role->code, $item['except'], true))
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
            Верстка: галочки пока не сохраняются и ни на что не влияют — состояние строится из <code>config/permissions.php</code>.
        </p>
    </div>
</x-app-layout>
