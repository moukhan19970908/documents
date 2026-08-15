<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $document->title }}</title>
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
        .badge-done    { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #e5e7eb; color: #374151; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
        .comment-cell { max-width: 200px; word-wrap: break-word; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $title }}</h1>
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
    </div>

    <div class="section-title">{{ $phase === 'ack' ? 'Ознакомление' : 'Приём' }}</div>

    @if($stages->isNotEmpty())
        @foreach($stages as $stage)
            <table>
                <thead>
                    <tr>
                        <th colspan="4">{{ $stage->workflowStage?->name ?? ($phase === 'ack' ? 'Ознакомление' : 'Приём') }}</th>
                    </tr>
                    <tr>
                        <th>{{ $phase === 'ack' ? 'Ознакомлен' : 'Принял' }}</th>
                        <th>Отдел</th>
                        <th>Отметка</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stage->workflowStage?->approvers ?? [] as $approver)
                        @php
                            // Последнее слово за последним решением: «исполнено» перекрывает «принято».
                            $decision = $stage->decisions->where('user_id', $approver->approver_id)->sortByDesc('id')->first();
                            $mark = match ($decision?->action) {
                                'acknowledge' => 'Ознакомлен',
                                'accept'      => 'Принято к исполнению',
                                'execute'     => 'Исполнено',
                                default       => null,
                            };
                        @endphp
                        <tr>
                            <td>{{ $approver->user?->name ?? '—' }}</td>
                            <td>{{ $approver->user?->department?->name ?? '—' }}</td>
                            <td>
                                @if($mark)
                                    <span class="badge badge-done">{{ $mark }}</span>
                                @else
                                    <span class="badge badge-pending">ОЖИДАЕТ</span>
                                @endif
                            </td>
                            <td>{{ $decision?->decided_at ? $decision->decided_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;color:#888">Нет участников</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @else
        <p style="color:#888;margin-bottom:15px">Фаза «{{ $phase === 'ack' ? 'Ознакомление' : 'Приём' }}» в маршрут не входила</p>
    @endif

    <div class="footer">
        Документ сформирован: {{ now()->format('d.m.Y H:i:s') }} | Vamin &copy; {{ now()->year }}
    </div>

</body>
</html>
