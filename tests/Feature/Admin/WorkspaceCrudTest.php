<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_use_workspace_crud_api(): void
    {
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin);

        $create = $this->postJson('/admin/api/workspaces', [
            'name' => 'Growth Studio',
            'slug' => 'growth-studio',
            'primary_colour' => '#14232d',
            'accent_colour' => '#8fcf00',
            'settings' => ['timezone' => 'Asia/Phnom_Penh'],
            'is_active' => true,
        ])->assertCreated();

        $workspaceId = $create->json('data.id');

        $this->getJson('/admin/api/workspaces')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'growth-studio']);

        $this->patchJson("/admin/api/workspaces/{$workspaceId}", [
            'name' => 'Growth Studio Updated',
            'slug' => 'growth-studio',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Growth Studio Updated');

        $this->postJson("/admin/api/workspaces/{$workspaceId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/admin/api/workspaces/{$workspaceId}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('workspaces', ['id' => $workspaceId, 'name' => 'Growth Studio Updated']);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.created', 'workspace_id' => $workspaceId]);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.updated', 'workspace_id' => $workspaceId]);
    }

    public function test_workspace_admin_cannot_manage_workspaces(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $admin = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin);

        $this->get('/admin/workspaces')->assertForbidden();
        $this->postJson('/admin/api/workspaces', ['name' => 'Denied'])->assertForbidden();
    }

    public function test_workspace_validation_errors_are_returned(): void
    {
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);
        Workspace::query()->create(['name' => 'Existing', 'slug' => 'existing']);

        $this->actingAs($superAdmin)
            ->postJson('/admin/api/workspaces', [
                'name' => '',
                'slug' => 'existing',
                'logo' => UploadedFile::fake()->create('logo.pdf', 12, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug', 'logo']);

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_workspace_slug_is_generated_from_name_when_omitted(): void
    {
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/admin/api/workspaces', [
                'name' => 'Bough Digital',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'bough-digital');

        $this->assertDatabaseHas('workspaces', ['name' => 'Bough Digital', 'slug' => 'bough-digital']);
    }

    public function test_workspace_logo_is_uploaded_into_id_based_workspace_storage(): void
    {
        Storage::fake('public');

        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $create = $this->actingAs($superAdmin)
            ->post('/admin/api/workspaces', [
                'name' => 'Logo Workspace',
                'slug' => 'logo-workspace',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonMissingPath('data.logo_path');

        $workspace = Workspace::query()->findOrFail($create->json('data.id'));

        $this->assertStringStartsWith("workspaces/{$workspace->id}/logo/logo-", $workspace->logo_path);
        $this->assertSame('public', $workspace->logo_disk);
        $this->assertSame(Storage::disk('public')->url($workspace->logo_path), $create->json('data.logo_url'));
        Storage::disk('public')->assertExists($workspace->logo_path);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.logo_uploaded', 'workspace_id' => $workspace->id]);
    }

    public function test_workspace_logo_replacement_removes_the_old_file_only_after_saving_the_new_one(): void
    {
        Storage::fake('public');

        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspace = Workspace::query()->create(['name' => 'Logo Workspace', 'slug' => 'logo-workspace']);

        $this->actingAs($superAdmin)->post("/admin/api/workspaces/{$workspace->id}", [
            '_method' => 'PATCH',
            'logo' => UploadedFile::fake()->image('first.jpg', 200, 80),
        ], ['Accept' => 'application/json'])->assertOk();

        $firstPath = $workspace->refresh()->logo_path;

        $this->post("/admin/api/workspaces/{$workspace->id}", [
            '_method' => 'PATCH',
            'logo' => UploadedFile::fake()->image('second.jpg', 200, 80),
        ], ['Accept' => 'application/json'])->assertOk();

        $secondPath = $workspace->refresh()->logo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.logo_replaced', 'workspace_id' => $workspace->id]);

        $this->post("/admin/api/workspaces/{$workspace->id}", [
            '_method' => 'PATCH',
            'remove_logo' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($secondPath);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'logo_path' => null, 'logo_disk' => null]);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.logo_removed', 'workspace_id' => $workspace->id]);
    }

    public function test_slug_change_keeps_logo_in_place_because_storage_is_keyed_by_id(): void
    {
        Storage::fake('public');

        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspace = Workspace::query()->create(['name' => 'Logo Workspace', 'slug' => 'logo-workspace']);
        $workspace->forceFill(['logo_disk' => 'public', 'logo_path' => "workspaces/{$workspace->id}/logo/logo-abc.jpg"])->save();
        Storage::disk('public')->put($workspace->logo_path, 'logo');

        $this->actingAs($superAdmin)
            ->patchJson("/admin/api/workspaces/{$workspace->id}", ['slug' => 'renamed-workspace'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'renamed-workspace');

        $this->assertSame("workspaces/{$workspace->id}/logo/logo-abc.jpg", $workspace->refresh()->logo_path);
        Storage::disk('public')->assertExists($workspace->logo_path);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.slug_changed', 'workspace_id' => $workspace->id]);
    }

    public function test_workspace_slugs_cannot_use_reserved_system_routes(): void
    {
        $superAdmin = User::factory()->create([
            'workspace_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);
        $workspace = Workspace::query()->create(['name' => 'Existing', 'slug' => 'existing']);

        $this->actingAs($superAdmin)
            ->postJson('/admin/api/workspaces', [
                'name' => 'MCP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->patchJson("/admin/api/workspaces/{$workspace->id}", [
            'slug' => 'admin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_workspace_admin_cannot_delete_workspace(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $admin = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/admin/api/workspaces/{$workspace->id}", ['confirmation' => $workspace->slug])
            ->assertForbidden();

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_workspace_delete_requires_confirmation(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspace = Workspace::query()->create(['name' => 'Delete Me', 'slug' => 'delete-me']);

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/workspaces/{$workspace->id}", ['confirmation' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_super_admin_can_delete_an_inactive_empty_workspace_without_losing_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspace = Workspace::query()->create(['name' => 'Delete Me', 'slug' => 'delete-me', 'is_active' => false]);
        $otherWorkspace = Workspace::query()->create(['name' => 'Keep Me', 'slug' => 'keep-me']);
        $member = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Writer]);
        $historyId = DB::table('activity_log')->insertGetId(['workspace_id' => $workspace->id, 'action' => 'workspace.updated', 'source' => 'portal', 'created_at' => now()]);

        Storage::disk('public')->put("workspaces/{$workspace->id}/logo/logo.png", 'logo');
        Storage::disk('public')->put("workspaces/{$otherWorkspace->id}/logo/logo.png", 'other');

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/workspaces/{$workspace->id}", ['confirmation' => $workspace->slug])
            ->assertOk();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id, 'workspace_id' => null]);
        $this->assertDatabaseHas('activity_log', ['id' => $historyId, 'workspace_id' => null]);
        $this->assertDatabaseHas('activity_log', ['action' => 'workspace.deleted', 'workspace_id' => null]);
        Storage::disk('public')->assertMissing("workspaces/{$workspace->id}/logo/logo.png");
        Storage::disk('public')->assertExists("workspaces/{$otherWorkspace->id}/logo/logo.png");
    }

    public function test_active_workspaces_and_workspaces_with_business_data_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $active = Workspace::query()->create(['name' => 'Active', 'slug' => 'active-workspace']);
        $withClients = Workspace::query()->create(['name' => 'Has Clients', 'slug' => 'has-clients', 'is_active' => false]);
        Client::query()->create(['workspace_id' => $withClients->id, 'name' => 'Client A', 'slug' => 'client-a']);

        $this->actingAs($superAdmin)
            ->deleteJson("/admin/api/workspaces/{$active->id}", ['confirmation' => $active->slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('workspace');

        $this->deleteJson("/admin/api/workspaces/{$withClients->id}", ['confirmation' => $withClients->slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('workspace');

        $this->assertDatabaseHas('workspaces', ['id' => $active->id]);
        $this->assertDatabaseHas('clients', ['workspace_id' => $withClients->id]);
    }

    public function test_storage_settings_mask_credentials_and_test_local_connection(): void
    {
        Storage::fake('local');
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->patchJson('/admin/api/storage-settings', [
                'driver' => 's3',
                's3' => [
                    'access_key_id' => 'AKIA123456789',
                    'secret_access_key' => 'super-secret-key',
                    'region' => 'us-east-1',
                    'bucket' => 'bough',
                ],
            ])
            ->assertOk()
            ->assertJsonMissing(['secret_access_key' => 'super-secret-key'])
            ->assertJsonPath('data.s3.secret_access_key', '********-key');

        $this->getJson('/admin/api/storage-settings')
            ->assertOk()
            ->assertJsonMissing(['access_key_id' => 'AKIA123456789'])
            ->assertJsonMissing(['secret_access_key' => 'super-secret-key']);

        $this->postJson('/admin/api/storage-settings/test', ['driver' => 'local'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_storage_switch_does_not_migrate_existing_files(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        Storage::disk('local')->put('workspaces/1/documents/existing.pdf', 'local');

        $this->actingAs($superAdmin)
            ->patchJson('/admin/api/storage-settings', [
                'driver' => 's3',
                's3' => [
                    'access_key_id' => 'AKIA123456789',
                    'secret_access_key' => 'super-secret-key',
                    'region' => 'us-east-1',
                    'bucket' => 'bough',
                ],
            ])
            ->assertOk();

        Storage::disk('local')->assertExists('workspaces/1/documents/existing.pdf');
        Storage::disk('s3')->assertMissing('workspaces/1/documents/existing.pdf');
    }
}
