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
                            <p style="font-size:15px; color:#111827; margin:0 0 16px;">Здравствуйте, {{ $user->name }}!</p>
                            <p style="font-size:14px; color:#374151; line-height:1.6; margin:0 0 20px;">
                                Для вас создан доступ внешнего участника в системе документооборота. Используйте данные ниже для входа:
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; color:#6B7280;">Логин (email)</td>
                                    <td style="padding:14px 18px; font-size:13px; color:#111827; font-weight:bold;" align="right">{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; color:#6B7280; border-top:1px solid #E5E7EB;">Пароль</td>
                                    <td style="padding:14px 18px; font-size:13px; color:#111827; font-weight:bold; border-top:1px solid #E5E7EB;" align="right">{{ $password }}</td>
                                </tr>
                            </table>

                            <a href="{{ $loginUrl }}"
                               style="display:inline-block; background:#5B4FE8; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; padding:12px 24px; border-radius:8px;">
                                Войти в систему
                            </a>

                            <p style="font-size:12px; color:#9CA3AF; line-height:1.6; margin:24px 0 0;">
                                Рекомендуем сменить пароль после первого входа. Если вы не ожидали это письмо, просто проигнорируйте его.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
