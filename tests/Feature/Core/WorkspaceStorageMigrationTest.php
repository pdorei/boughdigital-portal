<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class WorkspaceStorageMigrationTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    public function test_slug_based_logos_move_to_id_based_paths_without_losing_files(): void
    {
        Storage::fake('public');

        $workspace = $this->workspace('bough');
        $workspace->forceFill(['logo_disk' => 'public', 'logo_path' => 'workspaces/bough/logo/logo.jpg'])->save();
        Storage::disk('public')->put('workspaces/bough/logo/logo.jpg', 'logo-bytes');

        $missing = $this->workspace('missing-logo');
        $missing->forceFill(['logo_disk' => 'public', 'logo_path' => 'workspaces/missing-logo/logo/logo.jpg'])->save();

        $this->artisan('workspaces:migrate-storage', ['--dry-run' => true])->assertFailed();
        $this->assertSame('workspaces/bough/logo/logo.jpg', $workspace->refresh()->logo_path);

        $this->artisan('workspaces:migrate-storage')->assertFailed();

        $this->assertSame("workspaces/{$workspace->id}/logo/logo.jpg", $workspace->refresh()->logo_path);
        $this->assertSame('logo-bytes', Storage::disk('public')->get($workspace->logo_path));
        Storage::disk('public')->assertMissing('workspaces/bough/logo/logo.jpg');

        // A record whose file is missing is reported and left untouched.
        $this->assertSame('workspaces/missing-logo/logo/logo.jpg', $missing->refresh()->logo_path);

        $missing->forceFill(['logo_path' => null, 'logo_disk' => null])->save();
        $this->artisan('workspaces:migrate-storage')->expectsOutputToContain('0 problem(s)')->assertSuccessful();
        $this->assertSame("workspaces/{$workspace->id}/logo/logo.jpg", $workspace->refresh()->logo_path);
    }
}
