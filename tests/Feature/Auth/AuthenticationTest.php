<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_exists(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_super_admin_root_redirects_to_admin_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin)
            ->get('/')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_workspace_roles_root_redirect_to_their_workspace_dashboard(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);

        foreach ([UserRole::Admin, UserRole::TeamLeader, UserRole::Employee, UserRole::Writer, UserRole::Va] as $role) {
            $user = User::factory()->create([
                'workspace_id' => $workspace->id,
                'role' => $role,
            ]);

            $this->actingAs($user)
                ->get('/')
                ->assertRedirect('/bough/dashboard');
        }
    }

    public function test_unauthenticated_workspace_route_redirects_to_login(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);

        $this->get("/{$workspace->slug}/dashboard")
            ->assertRedirect('/login');
    }

    public function test_valid_workspace_login_redirects_to_workspace_dashboard(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        User::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/bough/dashboard');

        $this->assertAuthenticated();
        $this->assertNotNull(User::query()->where('email', 'admin@example.com')->value('last_login'));
    }

    public function test_workspace_login_ignores_forbidden_admin_intended_url(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Churchill', 'slug' => 'churchill']);
        User::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'admin@churchill.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $this->withSession(['url.intended' => 'http://portal.local/admin/dashboard'])
            ->post('/login', [
                'email' => 'admin@churchill.com',
                'password' => 'password',
            ])->assertRedirect('/churchill/dashboard');

        $this->assertAuthenticated();
    }

    public function test_workspace_login_keeps_own_workspace_intended_url(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Churchill', 'slug' => 'churchill']);
        User::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'admin@churchill.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $this->withSession(['url.intended' => 'http://portal.local/churchill/team'])
            ->post('/login', [
                'email' => 'admin@churchill.com',
                'password' => 'password',
            ])->assertRedirect('http://portal.local/churchill/team');

        $this->assertAuthenticated();
    }

    public function test_super_admin_login_redirects_to_admin_dashboard(): void
    {
        User::factory()->create([
            'workspace_id' => null,
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
        ]);

        $this->post('/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_authenticated_user_login_page_redirects_away_from_login(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $workspaceAdmin = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => UserRole::Admin,
        ]);
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($workspaceAdmin)
            ->get('/login')
            ->assertRedirect('/bough/dashboard');

        $this->actingAs($superAdmin)
            ->get('/login')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_remember_me_login_sets_remember_token(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'remember_me' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->remember_token);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->post('/forgot-password', ['email' => 'admin@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
    }

    public function test_workspace_user_can_access_own_workspace_but_not_admin_dashboard(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($user)->get('/bough/dashboard')->assertOk();
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_dashboard_security_by_role(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);

        $this->get('/admin/dashboard')->assertRedirect('/login');

        foreach ([UserRole::Admin, UserRole::TeamLeader, UserRole::Writer, UserRole::Va] as $role) {
            $user = User::factory()->create([
                'workspace_id' => $workspace->id,
                'role' => $role,
            ]);

            $this->actingAs($user)
                ->get('/admin/dashboard')
                ->assertForbidden();
        }

        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_wrong_workspace_user_cannot_access_workspace_dashboard(): void
    {
        Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $otherWorkspace = Workspace::query()->create(['name' => 'Other Workspace', 'slug' => 'other']);
        $otherAdmin = User::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($otherAdmin)
            ->get('/bough/dashboard')
            ->assertForbidden();
    }
}
