<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лист согласования — {{ $document->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #000; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }
        .meta { display: flex; gap: 30px; margin-bottom: 20px; }
        .meta-item { flex: 1; }
        .meta-item .label { font-size: 9px; text-transform: uppercase; color: #888; font-weight: bold; letter-spacing: 0.5px; }
        .meta-item .value { font-size: 12px; margin-top: 2px; font-weight: bold; }
        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        thead tr { background: #f0f0f0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-pending  { background: #e5e7eb; color: #374151; }
        .signature-row { display: flex; gap: 40px; margin-top: 40px; }
        .signature-block { flex: 1; }
        .signature-block .sig-line { border-bottom: 1px solid #000; margin-bottom: 4px; height: 30px; }
        .signature-block .sig-label { font-size: 9px; color: #555; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
        .page-break { page-break-after: always; }
        .comment-cell { max-width: 200px; word-wrap: break-word; }

        /* Штамп-удостоверение + фоновый водяной знак (только для согласованного документа) */
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center;
            font-size: 96px; font-weight: bold; color: #0a7d3c; opacity: 0.09;
            transform: rotate(-22deg); letter-spacing: 10px; z-index: 0; }
        .sheet-content { position: relative; z-index: 1; }
        .cert-stamp { width: 350px; margin: 0 2px 18px auto; border: 2.5px solid #0a7d3c;
            border-radius: 8px; padding: 10px 14px; color: #0a7d3c; background: rgba(10,125,60,0.05);
            transform: rotate(-2deg); position: relative; z-index: 2; }
        .cert-stamp .cert-title { font-size: 14px; font-weight: bold; text-align: center; letter-spacing: 1px; text-transform: uppercase; }
        .cert-stamp .cert-sub { font-size: 8px; text-align: center; text-transform: uppercase; letter-spacing: 1px;
            padding-bottom: 6px; margin-bottom: 6px; border-bottom: 1px solid rgba(10,125,60,0.35); }
        .cert-stamp table { width: 100%; border-collapse: collapse; margin: 0; }
        .cert-stamp td { border: none; padding: 2px 0; font-size: 9px; color: #0a5c2c; vertical-align: top; }
        .cert-stamp td.k { font-weight: bold; white-space: nowrap; padding-right: 8px; }
        .cert-stamp a { color: #0a7d3c; word-break: break-all; }
        .cert-stamp .cert-status { margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(10,125,60,0.35);
            font-size: 10px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>

@php
    // Штамп-удостоверение показываем только у согласованного документа.
    $isCertified = $approval && in_array($document->status, ['approved', 'signed', 'archived']);

    // «Утвердил»: сначала решение в фазе утверждения, иначе последнее «Согласовать» по маршруту.
    $approveDecision = null;
    if ($approval) {
        foreach ($approval->stages->sortByDesc(fn ($s) => $s->workflowStage?->sort_order ?? 0) as $s) {
            if (($s->workflowStage?->phase) === 'approve') {
                $approveDecision = $s->decisions->where('action', 'approve')->sortByDesc('decided_at')->first();
                if ($approveDecision) { break; }
            }
        }
        $approveDecision ??= $approval->stages
            ->flatMap(fn ($s) => $s->decisions)
            ->where('action', 'approve')
            ->sortByDesc('decided_at')
            ->first();
    }
    $approver = $approveDecision?->user;
    $certDate = optional($approval?->completed_at)->format('d.m.Y') ?: optional($document->registered_at)->format('d.m.Y');
    $docLink  = route('documents.show', $document->id);
@endphp

@if($isCertified)
    <div class="watermark">СОГЛАСОВАНО</div>
@endif

<div class="sheet-content">

    <div class="header">
        <h1>ЛИСТ СОГЛАСОВАНИЯ</h1>
        <p>Vamin — Система электронного документооборота</p>
    </div>

    <div class="meta">
        <div class="meta-item">
            <div class="label">Документ</div>
            <div class="value">{{ $document->title }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Инициатор</div>
            <div class="value">{{ $document->initiator->name }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Дата создания</div>
            <div class="value">{{ $document->created_at->format('d.m.Y') }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Статус</div>
            <div class="value">{{ $document->status_label }}</div>
        </div>
    </div>

    @if($isCertified)
        <div class="cert-stamp">
            <div class="cert-title">Документ согласован</div>
            <div class="cert-sub">Внутренний штамп-удостоверение</div>
            <table>
                <tr><td class="k">Наименование:</td><td>{{ $document->title }}</td></tr>
                <tr><td class="k">Дата согласования:</td><td>{{ $certDate ?: '—' }}</td></tr>
                <tr><td class="k">Лист согласования:</td><td>№ {{ $approval->id }}</td></tr>
                <tr><td class="k">Утвердил:</td><td>{{ $approver?->name ?? '—' }}@if($approver?->position) ({{ $approver->position }})@endif</td></tr>
                <tr><td class="k">ID документа:</td><td>{{ $document->id }}@if($document->number) · {{ $document->number }}@endif</td></tr>
                <tr><td class="k">Подлинность:</td><td><a href="{{ $docLink }}">{{ $docLink }}</a></td></tr>
            </table>
            <div class="cert-status">✔ Действителен, изменения запрещены</div>
        </div>
    @endif

    @if($document->data)
        <div class="section-title">Реквизиты документа</div>
        <table>
            <tbody>
                @foreach($document->data as $key => $val)
                    <tr>
                        <td style="width:40%; font-weight:bold">{{ $key }}</td>
                        <td>{{ $val }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Маршрут согласования</div>
    @if($approval && $approval->stages->isNotEmpty())
        @foreach($approval->stages->sortBy('order') as $stage)
            <table>
                <thead>
                    <tr>
                        <th colspan="5">Этап {{ $loop->iteration }}: {{ $stage->workflowStage?->name ?? 'Этап согласования' }}</th>
                    </tr>
                    <tr>
                        <th>Согласующий</th>
                        <th>Должность</th>
                        <th>Решение</th>
                        <th>Дата</th>
                        <th>Комментарий</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stage->decisions as $decision)
                        <tr>
                            <td>{{ $decision->user->name }}</td>
                            <td>{{ $decision->user->department?->name ?? '—' }}</td>
                            <td>
                                @if($decision->action === 'approve')
                                    <span class="badge badge-approved">Подписано</span>
                                @elseif($decision->action === 'reject')
                                    <span class="badge badge-rejected">ОТКЛОНЕНО</span>
                                @else
                                    <span class="badge badge-pending">ОЖИДАЕТ</span>
                                @endif
                            </td>
                            <td>{{ $decision->decided_at ? $decision->decided_at->format('d.m.Y H:i') : '—' }}</td>
                            <td class="comment-cell">{{ $decision->comment ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;color:#888">Нет решений</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @else
        <p style="color:#888;margin-bottom:15px">Процесс согласования не запущен</p>
    @endif

    <div class="signature-row">
        <div class="signature-block">
            <div class="sig-line"></div>
            <div class="sig-label">Инициатор: {{ $document->initiator->name }}</div>
        </div>
        <div class="signature-block">
            <div class="sig-line"></div>
            <div class="sig-label">Руководитель отдела</div>
        </div>
        <div class="signature-block">
            <div class="sig-line"></div>
            <div class="sig-label">Дата и печать</div>
        </div>
    </div>

    <div class="footer">
        Документ сформирован: {{ now()->format('d.m.Y H:i:s') }} | Vamin &copy; {{ now()->year }}
    </div>

</div>{{-- /.sheet-content --}}

</body>
</html>
