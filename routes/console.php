<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
