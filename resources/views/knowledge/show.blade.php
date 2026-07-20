<x-app-layout>
    <x-slot name="title">{{ $material->title }} — База знаний</x-slot>

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('knowledge.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← База знаний</a>

        @if(session('success'))
            <div class="my-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="mt-2 mb-5">
            <div class="flex items-center gap-2 flex-wrap mb-2">
                <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-[#5B4FE8] font-medium">{{ $material->typeLabel() }}</span>
                @if($material->direction)
                    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $material->direction->name }}</span>
                @endif
                @if($material->department)
                    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $material->department->name }}</span>
                @endif
                @if($material->level)
                    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $material->levelLabel() }}</span>
                @endif
            </div>

            <h1 class="text-3xl font-bold text-gray-900 leading-tight">{{ $material->title }}</h1>

            <div class="flex items-center gap-4 mt-3 text-sm text-gray-400">
                @if($material->studyLabel())
                    <span class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $material->studyLabel() }}
                    </span>
                @endif
                @if($material->author)
                    <span>{{ $material->author->name }}</span>
                @endif
            </div>
        </div>

        <article class="prose prose-sm max-w-none bg-white border border-gray-200 rounded-xl p-6">
            @if($material->body)
                {!! $material->body !!}
            @else
                <p class="text-gray-400">Материал пока без содержимого.</p>
            @endif
        </article>
    </div>
</x-app-layout>
