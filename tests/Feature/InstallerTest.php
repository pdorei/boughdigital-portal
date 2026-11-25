<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Installer\InstallerService;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private const ADMIN_EMAIL = 'installer-test-admin@example.com';

    private string $dir;

    private ?string $appKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Swap the real installed() check back in (TestCase forces "installed").
        $this->app->forgetInstance(InstallerService::class);

        // Point Laravel at an empty folder and drop the key so the portal looks
        // uninstalled, without touching the real .env.
        $this->dir = storage_path('framework/testing/installer-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        $this->app->useEnvironmentPath($this->dir);
        $this->appKey = config('app.key');
        config(['app.key' => null]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_every_page_redirects_to_the_installer_until_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
        $this->get('/admin/dashboard')->assertRedirect('/install');
        $this->get('/bough/dashboard')->assertRedirect('/install');
    }

    public function test_an_existing_env_file_counts_as_installed(): void
    {
        // The file alone (no key configured) is enough to lock the installer.
        File::put($this->dir.'/.env', 'APP_NAME="Bough Digital Portal"');
        $this->assertNull(config('app.key'));

        $this->get('/install')->assertNotFound();
        $this->postJson('/install', [])->assertNotFound();
        $this->postJson('/install/database', [])->assertNotFound();

        $this->writeEnvFile();
        $this->get('/login')->assertOk();
    }

    public function test_a_configured_app_key_counts_as_installed_without_env_file(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->get('/install')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    public function test_installed_portal_api_and_mcp_routes_are_not_redirected(): void
    {
        $this->writeEnvFile();

        // Same responses as before the installer existed: auth runs, no /install redirect.
        $this->getJson('/bough/api/activity')->assertUnauthorized();
        $this->postJson('/mcp/bough', [])->assertUnauthorized();
    }

    public function test_installer_shows_requirements(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('__BOUGH_INSTALLER__', false)
            ->assertSee('pdo_pgsql');
    }

    public function test_installer_explains_when_front_end_is_not_built(): void
    {
        $this->app->usePublicPath($this->dir);

        $this->get('/install')
            ->assertStatus(503)
            ->assertSee('npm run build');
    }

    public function test_database_check_reports_connection_errors(): void
    {
        $this->postJson('/install/database', [
            'db_host' => '127.0.0.1',
            'db_port' => 1,
            'db_database' => 'nope_testing',
            'db_username' => 'nobody',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Could not connect to the database'))
            ->assertJsonValidationErrors('db_connection');
    }

    public function test_install_validates_the_form(): void
    {
        $this->postJson('/install', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['app_name', 'app_url', 'db_host', 'db_database', 'db_username', 'admin_email', 'admin_password']);

        $this->assertFileDoesNotExist($this->dir.'/.env');
    }

    public function test_redis_is_kept_when_reachable(): void
    {
        $values = app(InstallerService::class)->environmentValues($this->installData(), redis: true);

        $this->assertSame('redis', $values['CACHE_STORE']);
        $this->assertSame('redis', $values['QUEUE_CONNECTION']);
        $this->assertArrayNotHasKey('SESSION_DRIVER', $values);
    }

    public function test_full_install_writes_env_migrates_and_creates_the_super_admin(): void
    {
        $database = (string) config('database.connections.pgsql.database');

        // Belt and braces on top of TestCase: install runs migrate on this database.
        if (! str_ends_with($database, '_testing')) {
            $this->markTestSkipped("Refusing to run the installer against [{$database}].");
        }

        // Simulate a server without Redis.
        $this->app->instance(InstallerService::class, new class extends InstallerService
        {
            public function redisAvailable(): bool
            {
                return false;
            }
        });

        try {
            $this->postJson('/install', $this->installData())
                ->assertOk()
                ->assertJsonPath('message', 'Installed.')
                ->assertJsonPath('cache', 'database');

            // Read it back the way Laravel will, so quoting rules are covered too.
            $env = Dotenv::parse(File::get($this->dir.'/.env'));

            $this->assertSame('Bough Installer Test', $env['APP_NAME']);
            $this->assertSame('production', $env['APP_ENV']);
            $this->assertSame('false', $env['APP_DEBUG']);
            $this->assertSame('https://portal.example.com', $env['APP_URL']);
            $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $env['APP_KEY']);
            $this->assertSame('pgsql', $env['DB_CONNECTION']);
            $this->assertSame($database, $env['DB_DATABASE']);
            $this->assertSame('database', $env['CACHE_STORE']);
            $this->assertSame('database', $env['QUEUE_CONNECTION']);
            $this->assertSame('database', $env['SESSION_DRIVER']);

            $admin = User::query()->where('email', self::ADMIN_EMAIL)->sole();
            $this->assertSame(UserRole::SuperAdmin, $admin->role);
            $this->assertNull($admin->workspace_id);
            $this->assertTrue($admin->is_active);

            $this->get('/install')->assertNotFound();
        } finally {
            DB::table('users')->where('email', self::ADMIN_EMAIL)->delete();
        }
    }

    public function test_installer_never_takes_over_an_existing_workspace_user(): void
    {
        $database = (string) config('database.connections.pgsql.database');

        if (! str_ends_with($database, '_testing')) {
            $this->markTestSkipped("Refusing to run the installer against [{$database}].");
        }

        $this->artisan('migrate', ['--force' => true]);

        User::query()->create([
            'name' => 'Existing Writer',
            'email' => self::ADMIN_EMAIL,
            'password' => 'existing-password',
            'role' => UserRole::Writer,
            'is_active' => true,
        ]);

        try {
            $this->postJson('/install', $this->installData())
                ->assertServerError()
                ->assertJsonPath('message', fn (string $message) => str_contains($message, 'already belongs to a user'));

            $this->assertSame(UserRole::Writer, User::query()->where('email', self::ADMIN_EMAIL)->sole()->role);
            $this->assertFileDoesNotExist($this->dir.'/.env');
        } finally {
            DB::table('users')->where('email', self::ADMIN_EMAIL)->delete();
        }
    }

    /**
     * An installed checkout: a .env exists (the cookie/session stack also needs the key back).
     */
    private function writeEnvFile(): void
    {
        File::put($this->dir.'/.env', 'APP_NAME="Bough Digital Portal"');
        config(['app.key' => $this->appKey]);
    }

    /**
     * @return array<string, mixed>
     */
    private function installData(): array
    {
        $connection = config('database.connections.pgsql');

        return [
            'app_name' => 'Bough Installer Test',
            'app_url' => 'https://portal.example.com/',
            'app_env' => 'production',
            'db_host' => $connection['host'],
            'db_port' => (int) $connection['port'],
            'db_database' => $connection['database'],
            'db_username' => $connection['username'],
            'db_password' => $connection['password'] ?? '',
            'admin_name' => 'Installer Test Admin',
            'admin_email' => self::ADMIN_EMAIL,
            'admin_password' => 'correct-horse',
            'admin_password_confirmation' => 'correct-horse',
        ];
    }
}
