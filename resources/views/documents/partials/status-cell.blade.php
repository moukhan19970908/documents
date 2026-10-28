{{-- Статус документа: бейдж текущей фазы и полоска участников маршрута (кто подписал, кто ещё нет). --}}
@php
    $statusBadge = [
        'draft'            => 'bg-gray-100 text-gray-600',
        'in_review'        => 'bg-blue-100 text-blue-700',
        'requires_changes' => 'bg-orange-100 text-orange-700',
        'approved'         => 'bg-green-100 text-green-700',
        'signed'           => 'bg-green-100 text-green-700',
        'rejected'         => 'bg-red-100 text-red-700',
        'archived'         => 'bg-gray-100 text-gray-500',
    ][$doc->status] ?? 'bg-gray-100 text-gray-600';

    $statusLabel = $doc->status_label;

    // На согласовании бейдж отражает текущую фазу маршрута: утверждение,
    // заключение, ознакомление и приём получают свою надпись и цвет.
    if ($doc->status === 'in_review') {
        $phase = ($doc->activeApproval ?? $doc->latestApproval)?->stages
            ->firstWhere('status', 'in_progress')?->workflowStage?->kind();

        [$statusLabel, $statusBadge] = match($phase) {
            'approval' => ['На согласовании', 'bg-blue-100 text-blue-700'],
            'approve'  => ['На утверждении',  'bg-violet-100 text-violet-700'],
            'opinion'  => ['На заключении',   'bg-sky-100 text-sky-700'],
            'ack'      => ['На ознакомлении', 'bg-amber-100 text-amber-700'],
            'intake'   => ['На приёмке',      'bg-purple-100 text-purple-700'],
            default    => [$statusLabel, $statusBadge],
        };
    }

    // Согласование пройдено, но ознакомились не все — не «Одобрено», а «На ознакомлении».
    if ($doc->awaitingAck()) {
        $statusBadge = 'bg-amber-100 text-amber-700';
    }
@endphp
<span class="inline-flex px-2 py-1 text-xs font-semibold rounded {{ $statusBadge }}">
    {{ $statusLabel }}
</span>
@php
    $stages = $doc->activeApproval?->stages ?? collect();
    // Те же решения, что на схеме процесса: заключения, ознакомление
    // и исполнение тоже красят сегмент, а не только «согласовал/отклонил».
    $positiveActions = ['approve', 'process_approve', 'opinion_yes', 'acknowledge', 'execute'];
    $negativeActions = ['reject', 'process_reject', 'opinion_no'];
    if ($doc->status === 'draft') {
        $segments = collect([['color' => '#D1D5DB', 'label' => 'Черновик']]);
    } elseif ($doc->status === 'rejected') {
        $segments = collect([['color' => '#EF4444', 'label' => 'Отклонено']]);
    } else {
        $segments = collect([['color' => '#22C55E', 'label' => 'Инициатор: ' . $doc->initiator->name]]);
        foreach ($stages as $stage) {
            $approvers = $stage->workflowStage?->approvers ?? collect();
            if ($approvers->isEmpty()) {
                $color = match($stage->status) {
                    'approved'    => '#22C55E',
                    'rejected'    => '#EF4444',
                    'in_progress' => '#D1D5DB',
                    default       => '#D1D5DB',
                };
                $segments->push(['color' => $color, 'label' => $stage->workflowStage?->name ?? 'Стадия']);
            } elseif (($stage->workflowStage?->stage_type ?? 'sequential') === 'sequential') {
                // "Любой из согласующих": одной подписи достаточно, этап
                // завершён — показываем один сегмент, а не по каждому.
                $approved = $stage->decisions->whereIn('action', $positiveActions)->sortByDesc('decided_at')->first();
                $rejected = $stage->decisions->whereIn('action', $negativeActions)->sortByDesc('decided_at')->first();
                $signerName = fn($uid) => $approvers->firstWhere('approver_id', $uid)?->user?->name
                    ?? \App\Models\User::find($uid)?->name ?? '—';
                if ($approved) {
                    $segments->push(['color' => '#22C55E', 'label' => 'Подписал: ' . $signerName($approved->user_id)]);
                } elseif ($rejected) {
                    $segments->push(['color' => '#EF4444', 'label' => 'Не одобрил: ' . $signerName($rejected->user_id)]);
                } elseif ($stage->status === 'approved') {
                    $segments->push(['color' => '#22C55E', 'label' => 'Этап пройден']);
                } elseif ($stage->status === 'in_progress') {
                    $segments->push(['color' => '#D1D5DB', 'label' => 'На подписании (любой из согласующих)']);
                } else {
                    $segments->push(['color' => '#D1D5DB', 'label' => 'Ожидает']);
                }
            } else {
                foreach ($approvers as $ap) {
                    // Colour each approver by their OWN decision, not the stage status.
                    $decision = $stage->decisions->where('user_id', $ap->approver_id)->sortByDesc('decided_at')->first();
                    $action   = $decision?->action;
                    $name     = $ap->user?->name ?? '—';
                    if (in_array($action, $positiveActions)) {
                        $color = '#22C55E';
                        $label = 'Подписал: ' . $name;
                    } elseif (in_array($action, $negativeActions)) {
                        $color = '#EF4444';
                        $label = 'Не одобрил: ' . $name;
                    } elseif ($action === 'delegate') {
                        $color = '#3B82F6';
                        $label = 'Делегировал: ' . $name;
                    } elseif ($stage->status === 'in_progress') {
                        $color = '#D1D5DB';
                        $label = 'На подписании у: ' . $name;
                    } else {
                        $color = '#D1D5DB';
                        $label = 'Ожидает: ' . $name;
                    }
                    $segments->push(['color' => $color, 'label' => $label]);
                }
            }
        }
    }
@endphp
@if($segments->count() > 0)
    <div class="flex gap-0.5 mt-1.5" style="width:120px">
        @foreach($segments as $seg)
            <div title="{{ $seg['label'] }}" style="flex:1; height:4px; background:{{ $seg['color'] }}; border-radius:2px; cursor:default"></div>
        @endforeach
    </div>
@endif
