<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = app(TenantContext::class)->workspace();

        abort_unless($user && $user->is_active && $user->belongsToWorkspace($workspace), 403);

        return $next($request);
    }
}
