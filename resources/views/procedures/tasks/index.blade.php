<x-app-layout>
    <x-slot name="title">Задачи по процедурам — Vamin</x-slot>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Задачи по процедурам</h1>
        <p class="text-sm text-gray-500 mb-6">Задачи, порождённые чек-листами процедур</p>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif

        @include('procedures._tasks')
    </div>
</x-app-layout>
