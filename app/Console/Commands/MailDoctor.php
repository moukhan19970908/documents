<?php

namespace App\Console\Commands;

use App\Mail\TaskAssigned;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Диагностика почты: настройки, состояние очереди, последние провалы.
 * Отвечает на вопрос «письмо ушло или нет» без чтения логов руками.
 */
class MailDoctor extends Command
{
    protected $signature = 'mail:doctor {--send= : Отправить тестовое письмо на этот адрес}';

    protected $description = 'Проверить настройки почты, очередь и последние ошибки отправки';

    public function handle(): int
    {
        $this->line('');
        $this->components->info('Настройки');
        $this->table(['Параметр', 'Значение'], [
            ['Почтовый драйвер', config('mail.default')],
            ['SMTP хост',        config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port')],
            ['Отправитель',      config('mail.from.address')],
            ['Очередь',          config('queue.default')],
        ]);

        if (config('mail.default') === 'log') {
            $this->warn('Драйвер «log» — письма не уходят, а пишутся в storage/logs/laravel.log.');
        }

        // Очередь: письма ставятся в notifications, воркер должен слушать именно её.
        $this->components->info('Очередь');
        if (config('queue.default') === 'database') {
            $waiting = DB::table('jobs')->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');

            if ($waiting->isEmpty()) {
                $this->line('  Ожидающих заданий нет.');
            } else {
                foreach ($waiting as $queue => $n) {
                    $this->line("  {$queue}: {$n} ждёт обработки");
                }
                $this->warn('  Если числа не убывают — воркер не запущен или слушает другую очередь.');
                $this->line('  Нужен: php artisan queue:work --queue=default,notifications');
            }

            $oldest = DB::table('jobs')->min('created_at');
            if ($oldest) {
                $this->line('  Самое старое задание: ' . \Carbon\Carbon::createFromTimestamp($oldest)->diffForHumans());
            }

            $failed = DB::table('failed_jobs')->count();
            $this->line('  Провалившихся заданий: ' . $failed);

            foreach (DB::table('failed_jobs')->latest('failed_at')->limit(3)->get() as $row) {
                $this->line('   · ' . $row->failed_at . ' — ' . str($row->exception)->before("\n"));
            }
        }

        if ($to = $this->option('send')) {
            $this->components->info('Тестовое письмо');
            $user = new User(['name' => 'Проверка доставки', 'email' => $to]);

            try {
                // Синхронно и мимо очереди — проверяем именно SMTP, а не воркер.
                Mail::to($to)->send(new TaskAssigned(
                    $user, null, 'Проверка доставки почты',
                    'Если вы видите это письмо, SMTP настроен верно.',
                    url('/'), now()->addDay(), 'проверке',
                ));
                $this->components->info("Отправлено на {$to} напрямую через SMTP.");
            } catch (\Throwable $e) {
                $this->components->error('SMTP отказал: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
