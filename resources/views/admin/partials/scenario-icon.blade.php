@switch($icon ?? 'document')
    @case('chat')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class ?? 'w-4 h-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        @break
    @case('briefcase')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class ?? 'w-4 h-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.9 23.9 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v1m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        @break
    @case('shield')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class ?? 'w-4 h-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 3l7 4v5c0 4.97-2.91 9.52-7 11-4.09-1.48-7-6.03-7-11V7l7-4z"/></svg>
        @break
    @case('bank')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class ?? 'w-4 h-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 5H3l9-5zM5 10v7m5-7v7m4-7v7m5-7v7M3 20h18"/></svg>
        @break
    @default
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class ?? 'w-4 h-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
@endswitch
