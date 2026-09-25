<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DevelopmentUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'bough'],
            [
                'name' => 'Bough Digital',
                'primary_colour' => '#14232d',
                'accent_colour' => '#8fcf00',
                'settings' => [],
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Platform Super Admin',
                'workspace_id' => null,
                'password' => 'password',
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ],
        );

        $workspaceUsers = [
            ['Workspace Admin', 'admin@example.com', UserRole::Admin],
            ['Team Leader', 'teamleader@example.com', UserRole::TeamLeader],
            ['Employee', 'employee@example.com', UserRole::Employee],
            ['Writer', 'writer@example.com', UserRole::Writer],
            ['VA', 'va@example.com', UserRole::Va],
        ];

        foreach ($workspaceUsers as [$name, $email, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'workspace_id' => $workspace->id,
                    'name' => $name,
                    'password' => 'password',
                    'role' => $role,
                    'is_active' => true,
                ],
            );
        }
    }
}
