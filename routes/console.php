<?php

use App\Enums\UserRole;
use App\Jobs\Upwork\DiscoverUpworkJobs;
use App\Jobs\Upwork\ExpireUpworkItems;
use App\Models\User;
use App\Services\Upwork\UpworkJobDiscoveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bough:create-super-admin {email} {name}', function (string $email, string $name): int {
    $password = (string) \Laravel\Prompts\password(
        label: 'Password',
        required: true,
        validate: fn (string $value) => strlen($value) >= 12 ? null : 'Use at least 12 characters.',
    );

    User::query()->updateOrCreate(
        ['email' => $email],
        [
            'workspace_id' => null,
            'name' => $name,
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ],
    );

    $this->info("Super admin {$email} is ready.");

    return self::SUCCESS;
})->purpose('Create or update the first platform-level super admin without hard-coded credentials');

/*
| Upwork automation. The discovery check runs every minute but only starts a
| run when automation is on, Upwork is connected and the configured interval
| has passed since the last run.
*/
Schedule::call(function (): void {
    if (app(UpworkJobDiscoveryService::class)->isDue()) {
        DiscoverUpworkJobs::dispatch('schedule');
    }
})->everyMinute()->name('upwork:discover')->withoutOverlapping();

Schedule::job(new ExpireUpworkItems)->everyFiveMinutes()->name('upwork:expire')->withoutOverlapping();
