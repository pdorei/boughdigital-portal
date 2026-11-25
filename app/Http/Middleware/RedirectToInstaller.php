<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepended to the global stack so a fresh copy with no .env (and no app key)
 * goes straight to the installer instead of erroring. On an installed portal
 * it is a single file/config check and passes every request through unchanged,
 * including /{workspace}/api and /mcp.
 */
class RedirectToInstaller
{
    public function __construct(private InstallerService $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->installed() || $request->is('install', 'install/*')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
