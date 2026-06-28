<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalRestriction
{
    /**
     * External participants may only reach their own workspace: dashboard,
     * their documents, tasks, chats, archive and notifications. Everything
     * else (workflows, employees, trips, vacations, admin) is denied even
     * via a direct URL.
     */
    private const ALLOWED_PREFIXES = [
        'dashboard',
        'documents.',
        'tasks',
        'chats.',
        'archive.',
        'notifications.',
        'agreement.',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isExternal()) {
            $routeName = $request->route()?->getName() ?? '';

            $allowed = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                abort(403, 'Недоступно для внешних участников.');
            }
        }

        return $next($request);
    }
}
