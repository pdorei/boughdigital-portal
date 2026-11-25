<?php

namespace App\Services\Installer;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Predis\Client;
use RuntimeException;
use Throwable;

/**
 * First-run setup for a fresh copy of the portal.
 *
 * The portal counts as installed once a .env file exists or an application key
 * is configured (for hosts that provide environment variables without a .env).
 * Until then every request is sent to /install.
 */
class InstallerService
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'pdo_pgsql', 'session', 'tokenizer', 'xml'];

    public const DEFAULT_PORT = 5432;

    /**
     * Runs on every request (RedirectToInstaller), so it must stay a file and
     * config check: no database, cache or Redis calls.
     */
    public function installed(): bool
    {
        return file_exists(app()->environmentFilePath()) || filled(config('app.key'));
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP '.self::MIN_PHP.' or newer',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => 'You have PHP '.PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);

            $checks[] = [
                'label' => "PHP {$extension} extension",
                'ok' => $loaded,
                'detail' => $loaded ? 'Enabled' : 'Missing, enable it in php.ini',
            ];
        }

        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'project folder (for .env)' => app()->environmentPath(),
        ];

        foreach ($paths as $label => $path) {
            $writable = is_writable($path);

            $checks[] = [
                'label' => "Writable {$label}",
                'ok' => $writable,
                'detail' => $writable ? 'Writable' : 'Make this folder writable by the web server',
            ];
        }

        return $checks;
    }

    public function meetsRequirements(): bool
    {
        return collect($this->requirements())->every('ok');
    }

    /**
     * Either a production build or a running Vite dev server (the "hot" file).
     */
    public function assetsBuilt(): bool
    {
        return file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    }

    /**
     * Open a throwaway connection with the submitted settings. Throws when it can't.
     *
     * @param  array<string, mixed>  $db
     */
    public function testConnection(array $db): void
    {
        config(['database.connections.installer_check' => $this->connectionConfig($db)]);

        try {
            DB::connection('installer_check')->getPdo();
        } finally {
            DB::purge('installer_check');
        }
    }

    /**
     * Whether Redis answers with the configured (or default) connection settings.
     * Uses its own short-lived manager with tight timeouts so a missing server
     * fails fast instead of hanging the install request.
     */
    public function redisAvailable(): bool
    {
        $client = config('database.redis.client', 'phpredis');

        if ($client === 'phpredis' && ! extension_loaded('redis')) {
            return false;
        }

        if ($client === 'predis' && ! class_exists(Client::class)) {
            return false;
        }

        $manager = new RedisManager(app(), $client, [
            'options' => ['prefix' => ''],
            'installer_check' => [
                ...config('database.redis.default', []),
                'timeout' => 2,
                'read_timeout' => 2,
                'max_retries' => 0,
            ],
        ]);

        try {
            $manager->connection('installer_check')->command('ping');

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            rescue(fn () => $manager->purge('installer_check'), report: false);
        }
    }

    /**
     * Migrate the database, create the Super Admin, then write .env last so a
     * failure part way through leaves the installer ready to try again.
     *
     * @param  array<string, mixed>  $data
     * @return array{redis: bool}
     */
    public function install(array $data): array
    {
        config([
            'database.connections.pgsql' => $this->connectionConfig($data),
            'database.default' => 'pgsql',
        ]);
        DB::purge('pgsql');

        // Incremental migrate only: never fresh, refresh or wipe.
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Running the database migrations failed.');
        }

        $this->createSuperAdmin($data);

        $redis = $this->redisAvailable();

        $this->writeEnvironmentFile($this->environmentValues($data, $redis));

        return ['redis' => $redis];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|bool>
     */
    public function environmentValues(array $data, bool $redis): array
    {
        $production = $data['app_env'] === 'production';

        $values = [
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => $data['app_env'],
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_DEBUG' => ! $production,
            'APP_URL' => rtrim($data['app_url'], '/'),
            'LOG_LEVEL' => $production ? 'error' : 'debug',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'CACHE_STORE' => $redis ? 'redis' : 'database',
            'QUEUE_CONNECTION' => $redis ? 'redis' : 'database',
        ];

        // Sessions only move off Redis when the template uses it and Redis is unreachable.
        if (! $redis && $this->templateValue('SESSION_DRIVER') === 'redis') {
            $values['SESSION_DRIVER'] = 'database';
        }

        return $values;
    }

    /**
     * Portal roles live on users.role (UserRole enum). The Super Admin is a
     * platform user with no workspace. An existing workspace user is never
     * promoted or has their password reset by the installer.
     *
     * @param  array<string, mixed>  $data
     */
    private function createSuperAdmin(array $data): void
    {
        $existing = User::query()->where('email', $data['admin_email'])->first();

        if ($existing && $existing->role !== UserRole::SuperAdmin) {
            throw new RuntimeException('That email already belongs to a user in this database. Use a different email for the Super Admin.');
        }

        User::query()->updateOrCreate(
            ['email' => $data['admin_email']],
            [
                'name' => $data['admin_name'],
                'password' => $data['admin_password'],
                'role' => UserRole::SuperAdmin,
                'workspace_id' => null,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $db
     * @return array<string, mixed>
     */
    private function connectionConfig(array $db): array
    {
        return [
            ...config('database.connections.pgsql'),
            'url' => null,
            'host' => $db['db_host'],
            'port' => $db['db_port'],
            'database' => $db['db_database'],
            'username' => $db['db_username'],
            'password' => $db['db_password'] ?? '',
        ];
    }

    private function template(): string
    {
        $example = base_path('.env.example');

        return file_exists($example) ? (string) file_get_contents($example) : '';
    }

    private function templateValue(string $key): ?string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $this->template(), $match)) {
            return null;
        }

        return trim($match[1], " \t\"'");
    }

    /**
     * Fill in .env.example (uncommenting keys like "# DB_HOST=") and write it
     * atomically, so a half-written .env can never mark the portal as installed.
     *
     * @param  array<string, string|bool>  $values
     */
    private function writeEnvironmentFile(array $values): void
    {
        $contents = $this->template();

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatValue($value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents, 1)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        $path = app()->environmentFilePath();
        $temporary = $path.'.installing';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Could not write the .env file. Check the project folder is writable.');
        }
    }

    private function formatValue(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === '' || preg_match('/^[\w.\/:@+=,-]+$/', $value)) {
            return $value;
        }

        // Single quotes are literal in dotenv (no ${VAR} expansion), so prefer them.
        return str_contains($value, "'")
            ? '"'.addcslashes($value, '"\\$').'"'
            : "'{$value}'";
    }
}
