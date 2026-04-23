<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bitrix24Service
{
    private string $webhookUrl;
    private string $baseUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.bitrix24.webhook_url', '');
        $this->baseUrl    = config('services.bitrix24.base_url', '');
    }

    public function createTask(Document $document, User $assignee): ?string
    {
        if (empty($this->webhookUrl) || !$assignee->bitrix24_id) {
            return null;
        }

        try {
            $response = Http::post($this->webhookUrl . '/tasks.task.add', [
                'fields' => [
                    'TITLE'          => 'Согласование: ' . $document->title,
                    'RESPONSIBLE_ID' => $assignee->bitrix24_id,
                    'DESCRIPTION'    => route('documents.show', $document->id),
                    'DEADLINE'       => now()->addDays(3)->toIso8601String(),
                ],
            ]);

            $data = $response->json();
            return (string) ($data['result']['task']['id'] ?? null);
        } catch (\Exception $e) {
            Log::error('Bitrix24 createTask failed: ' . $e->getMessage());
            return null;
        }
    }

    public function completeTask(string $taskId): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        try {
            Http::post($this->webhookUrl . '/tasks.task.complete', ['taskId' => $taskId]);
        } catch (\Exception $e) {
            Log::error('Bitrix24 completeTask failed: ' . $e->getMessage());
        }
    }

    public function syncUsers(): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        try {
            $response = Http::post($this->webhookUrl . '/user.get', ['ACTIVE' => true]);
            $users = $response->json()['result'] ?? [];

            foreach ($users as $b24User) {
                User::updateOrCreate(
                    ['bitrix24_id' => (string) $b24User['ID']],
                    [
                        'name'  => trim(($b24User['NAME'] ?? '') . ' ' . ($b24User['LAST_NAME'] ?? '')),
                        'email' => $b24User['EMAIL'] ?? null,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 syncUsers failed: ' . $e->getMessage());
        }
    }

    public function getUserById(int $b24UserId): array
    {
        if (empty($this->webhookUrl)) {
            return [];
        }

        try {
            $response = Http::post($this->webhookUrl . '/user.get', ['ID' => $b24UserId]);
            return $response->json()['result'][0] ?? [];
        } catch (\Exception $e) {
            Log::error('Bitrix24 getUserById failed: ' . $e->getMessage());
            return [];
        }
    }
}
