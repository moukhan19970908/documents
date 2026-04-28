<?php

namespace App\Console\Commands;

use App\Services\Bitrix24Service;
use Illuminate\Console\Command;

class SyncBitrix24 extends Command
{
    protected $signature = 'bitrix24:sync
                            {--departments-only : Sync only departments}
                            {--users-only : Sync only users}';

    protected $description = 'Sync employees and org structure from Bitrix24';

    public function handle(Bitrix24Service $bitrix24): int
    {
        $syncDepts = !$this->option('users-only');
        $syncUsers = !$this->option('departments-only');

        if ($syncDepts) {
            $this->info('Syncing departments from Bitrix24...');
            $result = $bitrix24->syncDepartments();
            $this->line("  Created: {$result['created']}, Updated: {$result['updated']}");
        }

        if ($syncUsers) {
            $this->info('Syncing users from Bitrix24...');
            $result = $bitrix24->syncUsers();
            $this->line("  Created: {$result['created']}, Updated: {$result['updated']}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
