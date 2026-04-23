<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    public function __construct(private AuditService $auditService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->isMethod('POST', 'PUT', 'PATCH', 'DELETE')) {
            $this->auditService->log(
                $request->method() . ':' . $request->path(),
                null,
                null,
                $request->except(['password', 'password_confirmation', '_token'])
            );
        }

        return $response;
    }
}
