<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureWorkspaceAccess;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            require __DIR__.'/../routes/api.php';
            require __DIR__.'/../routes/ai.php';
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // AuthenticateSession signs out other sessions after a password change.
        $middleware->web(append: [AuthenticateSession::class, EnsureUserIsActive::class]);

        $middleware->alias([
            'workspace.resolve' => ResolveWorkspace::class,
            'workspace.access' => EnsureWorkspaceAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create()
    // The hosting document root is public_html rather than Laravel's default public.
    ->usePublicPath(dirname(__DIR__).'/public_html');
