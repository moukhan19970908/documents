<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number ?? $order->title }}</title>
    <style>
        {!! file_get_contents(resource_path('css/blank-sheet.css')) !!}

        @page { size: A4; margin: 20mm; }
        body { margin: 0; padding: 24px 0; background: #f3f4f6; }
        .meta { clear: both; color: #6b7280; font-size: 9pt; margin-top: 24px; }
        @media print { body { padding: 0; background: #fff; } }
    </style>
</head>
<body>
    {{-- Тот же лист, что в редакторе бланка: иначе шапка с обтеканием и таблицы без рамок разъезжаются. --}}
    <div class="blank-sheet">
        {!! $order->renderedBody() !!}

        @if($order->isPublished())
            @include('orders.partials.cert-stamp')
        @endif

        <div class="meta">
            Приказ {{ $order->number ?? '(черновик)' }}
            @if($order->effective_at) · вступает в силу {{ $order->effective_at->format('d.m.Y') }} @endif
            · инициатор: {{ $order->initiator->name }}
        </div>
    </div>

    {{-- В браузере (без DomPDF) сразу открываем печать → «Сохранить как PDF». --}}
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 200); });</script>
</body>
</html>
