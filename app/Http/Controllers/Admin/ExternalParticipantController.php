<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ExternalParticipantCredentials;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ExternalParticipantController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $participants = User::where('role', 'external')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.external-participants.index', compact('participants'));
    }

    public function create()
    {
        return view('admin.external-participants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $password = Str::password(12);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($password),
            'role'      => 'external',
            'is_active' => true,
        ]);

        // Send credentials via the queue (database queue connection).
        $mailQueued = true;

        try {
            Mail::to($user->email)->queue(
                new ExternalParticipantCredentials($user, $password, route('login'))
            );
        } catch (\Throwable $e) {
            $mailQueued = false;
            Log::error('Не удалось поставить письмо с доступом внешнего участника в очередь', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        $this->auditService->log('external_participant_created', $user);

        if (! $mailQueued) {
            return redirect()->route('admin.external-participants.index')
                ->with('success', 'Внешний участник создан, но письмо с паролем не было отправлено. Проверьте лог.');
        }

        return redirect()->route('admin.external-participants.index')
            ->with('success', 'Внешний участник создан. Пароль отправлен на почту.');
    }

    public function destroy(User $external_participant)
    {
        abort_unless($external_participant->role === 'external', 404);

        $this->auditService->log('external_participant_deactivated', $external_participant);
        $external_participant->update(['is_active' => false]);

        return redirect()->route('admin.external-participants.index')
            ->with('success', 'Внешний участник деактивирован.');
    }
}
