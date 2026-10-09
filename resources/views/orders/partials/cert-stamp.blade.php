{{-- Штамп-удостоверение опубликованного приказа на листе бланка; те же поля впечатываются в загруженный файл. --}}
<div class="cert-stamp">
    <div class="cert-title">Документ утвержден</div>
    <div class="cert-sub">Внутренний штамп-удостоверение</div>
    <table>
        @foreach($order->certificateFields() as $label => $value)
            <tr>
                <td class="cert-key">{{ $label }}:</td>
                <td>@if(filter_var($value, FILTER_VALIDATE_URL))<a href="{{ $value }}">{{ $value }}</a>@else{{ $value }}@endif</td>
            </tr>
        @endforeach
    </table>
    <div class="cert-status">✔ Действителен, изменения запрещены</div>
</div>
