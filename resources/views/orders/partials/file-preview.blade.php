{{--
    Загруженный файл приказа: шапка с именем и скачиванием + предпросмотр.
    PDF и картинки показывает браузер, DOCX рисует docx-preview (как у документов),
    остальные форматы — только скачать.
--}}
@php
    $previewMime = $order->filePreviewMime();
    $previewUrl  = route('orders.file.preview', $order);
@endphp

<div class="flex items-center justify-between gap-3 px-6 py-3 border-b border-gray-100">
    <p class="text-sm text-gray-700 truncate">📎 {{ $order->file_name }}</p>
    <a href="{{ route('orders.file', $order) }}"
       class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shrink-0">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Скачать файл
    </a>
</div>

@if($previewMime === 'application/pdf')
    <iframe src="{{ $previewUrl }}" title="{{ $order->file_name }}" class="w-full border-0 block" style="height:80vh"></iframe>
@elseif(str_starts_with((string) $previewMime, 'image/'))
    <div class="flex justify-center bg-gray-100 p-8">
        <img src="{{ $previewUrl }}" alt="{{ $order->file_name }}" class="max-w-full h-auto rounded-lg shadow">
    </div>
@elseif($previewMime !== null)
    {{-- DOCX --}}
    <div class="bg-gray-100 p-4 overflow-y-auto" style="height:80vh">
        <div id="order-docx-preview" class="w-full flex flex-col items-center gap-4"></div>
        <p id="order-docx-preview-error" class="hidden py-24 text-center text-sm text-gray-500">Не удалось показать файл — скачайте его.</p>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', async () => {
        const loadScript = (src) => new Promise((resolve, reject) => {
            if (document.querySelector('script[src="' + src + '"]')) { resolve(); return; }
            const s = document.createElement('script'); s.src = src;
            s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
        try {
            if (typeof JSZip === 'undefined') await loadScript('https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js');
            if (typeof docx === 'undefined')  await loadScript('https://cdn.jsdelivr.net/npm/docx-preview@0.3.4/dist/docx-preview.min.js');
            const res = await fetch(@js($previewUrl), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await docx.renderAsync(await res.arrayBuffer(), document.getElementById('order-docx-preview'), null,
                { className: 'docx-render', inWrapper: false, ignoreWidth: true, ignoreHeight: true, breakPages: true, useBase64URL: true });
        } catch (e) {
            console.error('Предпросмотр DOCX приказа не удался', e);
            document.getElementById('order-docx-preview-error').classList.remove('hidden');
        }
    });
    </script>
@else
    <div class="flex flex-col items-center justify-center gap-3 py-16 bg-gray-50 text-gray-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-sm">Предпросмотр для этого формата недоступен — скачайте файл.</p>
    </div>
@endif
