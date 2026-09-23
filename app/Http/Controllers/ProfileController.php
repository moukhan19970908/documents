<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()->load('department')]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        unset($validated['avatar']);
        $previousAvatar = $user->avatar;

        if ($file = $request->file('avatar')) {
            // Имя генерирует Laravel — путь всегда новый, поэтому ключ файлового кэша
            // в AvatarController и метка ?v= в avatar_url меняются сами собой.
            $validated['avatar'] = $file->store('avatars', 's3');
        }

        $old = $user->only(['name', 'email', 'phone', 'avatar']);
        $user->update($validated);
        $this->auditService->log('profile_updated', $user, $old, $user->only(['name', 'email', 'phone', 'avatar']));

        // Старый файл — только после успешной записи нового пути, иначе при сбое
        // пользователь остался бы и без старого аватара, и без нового.
        if ($previousAvatar && $user->avatar !== $previousAvatar) {
            Storage::disk('s3')->delete($previousAvatar);
            Storage::disk('local')->delete($user->avatarCachePath($previousAvatar));
        }

        return redirect()->route('profile.edit')->with('success', 'Профиль обновлён.');
    }

    public function editPassword()
    {
        return view('profile.password', ['user' => auth()->user()]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        // У пользователей из Битрикс24 пароля нет (вход через OAuth) — им нечего
        // подтверждать, они задают первый пароль.
        $rules = ['password' => ['required', 'string', 'min:8', 'confirmed']];
        if ($user->password) {
            $rules['current_password'] = ['required', 'string'];
        }
        $request->validate($rules);

        if ($user->password && ! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Текущий пароль указан неверно.',
            ]);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);
        // Сам пароль ни в старых, ни в новых значениях не пишем.
        $this->auditService->log('profile_password_changed', $user);

        return redirect()->route('profile.password')->with('success', 'Пароль изменён.');
    }
}
