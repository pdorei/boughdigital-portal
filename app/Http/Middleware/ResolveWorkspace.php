<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('workspace');
        $workspace = Workspace::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        app(TenantContext::class)->setWorkspace($workspace);
        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
