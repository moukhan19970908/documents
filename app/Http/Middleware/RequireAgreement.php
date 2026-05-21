<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAgreement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->agreement_accepted_at) {
            if (!$request->routeIs('agreement.*')) {
                return redirect()->route('agreement.show');
            }
        }

        return $next($request);
    }
}
