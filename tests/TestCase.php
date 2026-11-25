<?php

namespace Tests;

use App\Services\Installer\InstallerService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run as an installed portal whether or not this checkout has a .env.
        // InstallerTest swaps the real check back in.
        $this->app->instance(InstallerService::class, new class extends InstallerService
        {
            public function installed(): bool
            {
                return true;
            }
        });
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $this->refuseNonTestingDatabase($app);

        return $app;
    }

    /**
     * Feature tests refresh the schema, so booting against the development
     * database (for example through a cached config) would wipe real data.
     */
    private function refuseNonTestingDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]. Tests must use a *_testing database (see phpunit.xml)."
            );
        }
    }
}
