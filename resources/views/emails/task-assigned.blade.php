<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#F9FAFB; font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
        <tr>
            <td align="center">
                <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff; border:1px solid #E5E7EB; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#5B4FE8; padding:20px 28px; color:#ffffff; font-size:18px; font-weight:bold;">
                            Vamin · Документооборот
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="font-size:15px; color:#111827; margin:0 0 16px;">Здравствуйте, {{ $assignee->name }}!</p>
                            <p style="font-size:14px; color:#374151; line-height:1.6; margin:0 0 20px;">
                                Вам поставлена задача@if($context) по {{ $context }}@endif.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; color:#6B7280;" width="130">Задача</td>
                                    <td style="padding:14px 18px; font-size:13px; color:#111827; font-weight:bold;">{{ $taskTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; color:#6B7280; border-top:1px solid #E5E7EB;">Постановщик</td>
                                    <td style="padding:14px 18px; font-size:13px; color:#111827; border-top:1px solid #E5E7EB;">{{ $assigner?->name ?? '—' }}</td>
                                </tr>
                                @if($deadline)
                                    <tr>
                                        <td style="padding:14px 18px; font-size:13px; color:#6B7280; border-top:1px solid #E5E7EB;">Срок</td>
                                        <td style="padding:14px 18px; font-size:13px; color:#111827; border-top:1px solid #E5E7EB;">
                                            {{ \Carbon\Carbon::parse($deadline)->format('d.m.Y H:i') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            @if(trim($taskDescription) !== '')
                                <p style="font-size:14px; color:#374151; line-height:1.6; margin:0 0 20px; white-space:pre-line;">{{ $taskDescription }}</p>
                            @endif

                            @if($url)
                                <a href="{{ $url }}"
                                   style="display:inline-block; background:#5B4FE8; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; padding:12px 24px; border-radius:8px;">
                                    Открыть в системе
                                </a>
                            @endif

                            <p style="font-size:12px; color:#9CA3AF; line-height:1.6; margin:24px 0 0;">
                                Письмо отправлено автоматически. Чтобы задать вопрос постановщику, ответьте на это письмо.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
