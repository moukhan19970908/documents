<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number ?? $order->title }}</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: 'DejaVu Sans', 'Times New Roman', serif; color: #111; font-size: 13px; line-height: 1.5; }
        table { border-collapse: collapse; }
        hr { border: none; border-top: 2px solid #5B4FE8; margin: 12px 0; }
        .meta { color: #6b7280; font-size: 11px; margin-top: 24px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    {!! $order->renderedBody() !!}

    <div class="meta">
        Приказ {{ $order->number ?? '(черновик)' }}
        @if($order->effective_at) · вступает в силу {{ $order->effective_at->format('d.m.Y') }} @endif
        · инициатор: {{ $order->initiator->name }}
    </div>

    {{-- В браузере (без DomPDF) сразу открываем печать → «Сохранить как PDF». --}}
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 200); });</script>
</body>
</html>
