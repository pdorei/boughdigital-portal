<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_standard_development_users_idempotently(): void
    {
        $this->seed(DevelopmentUserSeeder::class);
        $firstRunIds = User::query()->pluck('id', 'email');

        $this->seed(DevelopmentUserSeeder::class);

        $workspace = Workspace::query()->where('slug', 'bough')->sole();

        $expectedUsers = [
            'superadmin@example.com' => [UserRole::SuperAdmin, null],
            'admin@example.com' => [UserRole::Admin, $workspace->id],
            'teamleader@example.com' => [UserRole::TeamLeader, $workspace->id],
            'employee@example.com' => [UserRole::Employee, $workspace->id],
            'writer@example.com' => [UserRole::Writer, $workspace->id],
            'va@example.com' => [UserRole::Va, $workspace->id],
        ];

        $this->assertSame(6, User::query()->count());

        foreach ($expectedUsers as $email => [$role, $workspaceId]) {
            $user = User::query()->where('email', $email)->sole();

            $this->assertTrue($user->is_active);
            $this->assertTrue($user->role === $role);
            $this->assertSame($workspaceId, $user->workspace_id);
            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertSame($firstRunIds[$email], $user->id);
        }
    }
}
