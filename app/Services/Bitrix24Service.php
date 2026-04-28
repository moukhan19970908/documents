<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Sync departments from Bitrix24 using department.get API.
     * Returns count of created/updated records.
     */
    public function syncDepartments(): array
    {
        if (empty($this->webhookUrl)) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        try {
            // Fetch all departments (Bitrix24 returns up to 50 per call, paginate if needed)
            $start = 0;
            $allDepartments = [];

            do {
                $response = Http::post($this->webhookUrl . '/department.get', [
                    'start' => $start,
                ]);

                $data = $response->json();
                $batch = $data['result'] ?? [];
                $allDepartments = array_merge($allDepartments, $batch);

                $total = $data['total'] ?? count($allDepartments);
                $start += 50;
            } while (count($allDepartments) < $total);

            // First pass: create/update departments without resolving parent_id/head_user_id
            foreach ($allDepartments as $b24Dept) {
                $b24Id = (string) $b24Dept['ID'];

                $existing = Department::where('bitrix24_department_id', $b24Id)->first();

                if ($existing) {
                    $existing->update(['name' => $b24Dept['NAME']]);
                    $updated++;
                } else {
                    Department::create([
                        'name'                   => $b24Dept['NAME'],
                        'bitrix24_department_id' => $b24Id,
                    ]);
                    $created++;
                }
            }

            // Second pass: resolve parent_id and head_user_id
            foreach ($allDepartments as $b24Dept) {
                $dept = Department::where('bitrix24_department_id', (string) $b24Dept['ID'])->first();
                if (!$dept) {
                    continue;
                }

                $parentId = null;
                if (!empty($b24Dept['PARENT'])) {
                    $parent = Department::where('bitrix24_department_id', (string) $b24Dept['PARENT'])->first();
                    $parentId = $parent?->id;
                }

                $headUserId = null;
                if (!empty($b24Dept['UF_HEAD'])) {
                    $head = User::where('bitrix24_id', (string) $b24Dept['UF_HEAD'])->first();
                    $headUserId = $head?->id;
                }

                $dept->update([
                    'parent_id'    => $parentId,
                    'head_user_id' => $headUserId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bitrix24 syncDepartments failed: ' . $e->getMessage());
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Sync users from Bitrix24 using user.get API.
     * Returns count of created/updated records.
     */
    public function syncUsers(): array
    {
        if (empty($this->webhookUrl)) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        try {
            $start = 0;

            do {
                $response = Http::post($this->webhookUrl . '/user.get', [
                    'ACTIVE' => true,
                    'start'  => $start,
                ]);

                $data  = $response->json();
                $users = $data['result'] ?? [];

                foreach ($users as $b24User) {
                    $b24Id = (string) $b24User['ID'];

                    // Resolve department: UF_DEPARTMENT is an array of Bitrix24 dept IDs
                    $departmentId = null;
                    $b24DeptIds = (array) ($b24User['UF_DEPARTMENT'] ?? []);
                    if (!empty($b24DeptIds)) {
                        $dept = Department::where('bitrix24_department_id', (string) $b24DeptIds[0])->first();
                        $departmentId = $dept?->id;
                    }

                    $isActive  = (bool) ($b24User['ACTIVE'] ?? true);
                    $name      = trim(($b24User['NAME'] ?? '') . ' ' . ($b24User['LAST_NAME'] ?? ''));
                    $email     = $b24User['EMAIL'] ?? null;
                    $photoUrl  = $b24User['PERSONAL_PHOTO'] ?? null;
                    $position  = $b24User['WORK_POSITION'] ?? null;

                    $existing = User::where('bitrix24_id', $b24Id)->first();

                    if ($existing) {
                        $avatarPath = ($photoUrl && !$existing->avatar)
                            ? $this->downloadAvatar($photoUrl, $b24Id)
                            : $existing->avatar;

                        $existing->update([
                            'name'          => $name ?: $existing->name,
                            'email'         => $email ?: $existing->email,
                            'position'      => $position ?? $existing->position,
                            'department_id' => $departmentId ?? $existing->department_id,
                            'is_active'     => $isActive,
                            'avatar'        => $avatarPath,
                        ]);
                        $updated++;
                    } else {
                        $avatarPath = $photoUrl ? $this->downloadAvatar($photoUrl, $b24Id) : null;

                        // For new users without a password yet, mark inactive until they set one
                        User::create([
                            'name'          => $name ?: 'User #' . $b24Id,
                            'email'         => $email,
                            'password'      => null,
                            'role'          => 'linear',
                            'position'      => $position,
                            'department_id' => $departmentId,
                            'bitrix24_id'   => $b24Id,
                            'is_active'     => $isActive,
                            'avatar'        => $avatarPath,
                        ]);
                        $created++;
                    }
                }

                $total = $data['total'] ?? ($start + count($users));
                $start += 50;
            } while ($start < $total);
        } catch (\Exception $e) {
            Log::error('Bitrix24 syncUsers failed: ' . $e->getMessage());
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Download a Bitrix24 avatar URL and store it in public/avatars/.
     * Returns the stored path relative to the public disk, or null on failure.
     */
    private function downloadAvatar(string $url, string $b24Id): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'gif')  => 'gif',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };

            $path = 'avatars/b24_' . $b24Id . '.' . $ext;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Exception $e) {
            Log::warning('Bitrix24 downloadAvatar failed for user ' . $b24Id . ': ' . $e->getMessage());
            return null;
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
