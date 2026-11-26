<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_storage_settings_page(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('storage_settings', false);

        $this->get('/admin/system')->assertOk();

        $this->get('/admin/settings/storage')
            ->assertRedirect('/admin/settings?tab=storage');
    }

    public function test_workspace_user_cannot_access_system_storage_settings_page_or_api(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $admin = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/system')
            ->assertForbidden();

        $this->get('/admin/settings')
            ->assertForbidden();

        $this->getJson('/admin/api/storage-settings')
            ->assertForbidden();
    }

    public function test_local_connection_test_passes_and_cleans_up_temp_file(): void
    {
        Storage::fake('local');
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->postJson('/admin/api/storage-settings/test', ['driver' => 'local'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertCount(0, Storage::disk('local')->allFiles('storage-tests'));
    }

    public function test_s3_requires_complete_configuration_before_activation(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->patchJson('/admin/api/storage-settings', [
                'driver' => 's3',
                's3' => ['region' => 'us-east-1'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['s3.bucket', 's3.access_key_id', 's3.secret_access_key']);

        $this->assertSame('local', app(StorageSettingsService::class)->activeDisk());
    }

    public function test_s3_secret_is_encrypted_at_rest_and_masked_in_responses(): void
    {
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
            ->assertJsonPath('data.driver', 's3')
            ->assertJsonPath('data.s3.access_key_id', '********6789')
            ->assertJsonPath('data.s3.secret_access_key', '********-key')
            ->assertJsonMissing(['secret_access_key' => 'super-secret-key']);

        $encrypted = DB::table('application_settings')->where('key', 'storage')->value('encrypted_value');

        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString('super-secret-key', $encrypted);
        $this->assertStringContainsString('super-secret-key', Crypt::decryptString($encrypted));
    }

    public function test_switching_storage_updates_default_for_new_files_without_migrating_existing_file_records(): void
    {
        $superAdmin = User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin]);
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Client A', 'slug' => 'client-a']);
        $document = ClientDocument::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'title' => 'Existing Local File',
            'document_type' => 'file',
            'folder' => 'other',
            'storage_disk' => 'local',
            'storage_path' => "workspaces/{$workspace->id}/documents/existing.pdf",
        ]);

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

        $this->assertSame('s3', app(StorageSettingsService::class)->activeDisk());
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'storage_disk' => 'local',
            'storage_path' => "workspaces/{$workspace->id}/documents/existing.pdf",
        ]);
    }
}
