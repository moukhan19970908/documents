<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BitrixSocialiteController extends Controller
{
    public function redirect()
    {
        $clientId = config('services.bitrix24.client_id');
        $redirectUri = config('services.bitrix24.redirect');
        $baseUrl = config('services.bitrix24.base_url');

        if (empty($clientId) || empty($baseUrl)) {
            return redirect('/login')->with('error', 'Bitrix24 OAuth не настроен.');
        }

        $url = rtrim($baseUrl, '/') . '/oauth/authorize/?' . http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'scope'         => 'crm,user',
            'state'         => csrf_token(),
        ]);

        return redirect($url);
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');
        if (!$code) {
            return redirect('/login')->with('error', 'Авторизация через Bitrix24 отменена.');
        }

        try {
            $tokenResponse = \Illuminate\Support\Facades\Http::get(
                rtrim(config('services.bitrix24.base_url'), '/') . '/oauth/token/',
                [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => config('services.bitrix24.client_id'),
                    'client_secret' => config('services.bitrix24.client_secret'),
                    'redirect_uri'  => config('services.bitrix24.redirect'),
                    'code'          => $code,
                ]
            );

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $b24UserId = $tokenData['user_id'] ?? null;
            $memberDomain = $tokenData['client_endpoint'] ?? null;

            if (!$accessToken || !$b24UserId) {
                return redirect('/login')->with('error', 'Не удалось получить данные пользователя Bitrix24.');
            }

            // Get user info from Bitrix24
            $userResponse = \Illuminate\Support\Facades\Http::get(
                $memberDomain . 'user.current',
                ['auth' => $accessToken]
            );
            $b24User = $userResponse->json()['result'] ?? [];

            $user = User::updateOrCreate(
                ['bitrix24_id' => (string) $b24UserId],
                [
                    'name'           => trim(($b24User['NAME'] ?? '') . ' ' . ($b24User['LAST_NAME'] ?? 'B24 User')),
                    'email'          => $b24User['EMAIL'] ?? 'b24_' . $b24UserId . '@archmanuscript.local',
                    'bitrix24_token' => $accessToken,
                    'password'       => \Illuminate\Support\Facades\Hash::make(Str::random(32)),
                ]
            );

            Auth::login($user);
            $request->session()->regenerate();

            return redirect('/dashboard');
        } catch (\Exception $e) {
            dd($e->getMessage());
            Log::error('Bitrix24 OAuth callback failed: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Ошибка авторизации через Bitrix24.');
        }
    }
}
