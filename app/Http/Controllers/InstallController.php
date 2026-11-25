<?php

namespace App\Http\Controllers;

use App\Services\Installer\InstallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * These routes run outside the "web" middleware group: before install there is
 * no app key, session store or database for that stack to use. Every action
 * returns 404 once the portal is installed.
 */
class InstallController extends Controller
{
    public function __construct(private InstallerService $installer) {}

    public function show(Request $request): Response|View
    {
        $this->ensureNotInstalled();

        // public_html/build is git-ignored, so a fresh copy may not have it yet.
        if (! $this->installer->assetsBuilt()) {
            return response()->view('install.assets-missing', status: 503);
        }

        return view('install.index', [
            'installer' => [
                'requirements' => $this->installer->requirements(),
                'meetsRequirements' => $this->installer->meetsRequirements(),
                'defaults' => [
                    'appName' => 'Bough Digital Portal',
                    'appUrl' => $request->root(),
                    'dbPort' => InstallerService::DEFAULT_PORT,
                ],
                'routes' => [
                    'database' => route('install.database'),
                    'install' => route('install.store'),
                ],
                'versions' => ['laravel' => app()->version(), 'php' => PHP_VERSION],
            ],
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $this->ensureNotInstalled();

        $data = $request->validate($this->databaseRules());

        try {
            $this->installer->testConnection($data);
        } catch (Throwable $e) {
            return $this->connectionFailed($e);
        }

        return response()->json(['message' => 'Connected to the database.']);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureNotInstalled();

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url:http,https'],
            'app_env' => ['required', Rule::in(['production', 'local'])],
            ...$this->databaseRules(),
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->installer->meetsRequirements()) {
            return response()->json(['message' => 'This server does not meet the requirements yet.'], 422);
        }

        try {
            $this->installer->testConnection($data);
        } catch (Throwable $e) {
            return $this->connectionFailed($e);
        }

        try {
            $result = $this->installer->install($data);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Installation failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Installed.',
            'login' => url('/login'),
            'cache' => $result['redis'] ? 'redis' : 'database',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function connectionFailed(Throwable $e): JsonResponse
    {
        $message = 'Could not connect to the database: '.$e->getMessage();

        return response()->json(['message' => $message, 'errors' => ['db_connection' => [$message]]], 422);
    }

    private function ensureNotInstalled(): void
    {
        abort_if($this->installer->installed(), 404);
    }
}
