<?php

namespace Tests\Feature\Core;

use App\Enums\DocumentSource;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWorkspaceFixtures;
use Tests\TestCase;

class DocumentLibraryTest extends TestCase
{
    use CreatesWorkspaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_uploads_are_stored_privately_under_the_workspace_and_client_ids(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);

        $response = $this->actingAs($admin)->post('/bough/api/documents', [
            'kind' => 'upload',
            'client_id' => $client->id,
            'folder' => 'strategy',
            'document_type' => 'Proposal',
            'file' => UploadedFile::fake()->create('Q4 plan.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = ClientDocument::query()->findOrFail($response->json('data.id'));

        $this->assertStringStartsWith("workspaces/{$workspace->id}/clients/{$client->id}/documents/", $document->storage_path);
        $this->assertStringEndsWith('.pdf', $document->storage_path);
        $this->assertSame('local', $document->storage_disk);
        $this->assertSame('Q4 plan.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->storage_path);

        $response
            ->assertJsonPath('data.source', 'upload')
            ->assertJsonPath('data.title', 'Q4 plan')
            ->assertJsonPath('data.download_url', "/bough/api/documents/{$document->id}/download")
            ->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.storage_disk');

        $this->get("/bough/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertDownload('Q4 plan.pdf');

        $this->assertDatabaseHas('activity_log', ['action' => 'document.uploaded', 'client_id' => $client->id]);
    }

    public function test_uploads_are_validated(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);

        $this->actingAs($this->member($workspace))->post('/bough/api/documents', [
            'kind' => 'upload',
            'client_id' => $client->id,
            'folder' => 'drafts',
            'file' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'folder']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_links_are_recorded_as_links_and_google_docs_are_labelled_by_host(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);

        $this->actingAs($this->member($workspace));

        $this->postJson('/bough/api/documents', [
            'kind' => 'link', 'client_id' => $client->id, 'folder' => 'content', 'title' => 'Brief',
            'external_url' => 'https://docs.google.com/document/d/abc123/edit',
        ])->assertCreated()
            ->assertJsonPath('data.source', 'google_drive')
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.open_url', 'https://docs.google.com/document/d/abc123/edit');

        $this->postJson('/bough/api/documents', [
            'kind' => 'link', 'client_id' => $client->id, 'folder' => 'reports', 'title' => 'Dashboard',
            'external_url' => 'https://lookerstudio.example/report',
        ])->assertCreated()
            ->assertJsonPath('data.source', 'external_link');

        $this->getJson("/bough/api/documents?client_id={$client->id}&source=google_drive")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertDatabaseHas('activity_log', ['action' => 'document.linked', 'client_id' => $client->id]);
    }

    public function test_metadata_can_be_edited_and_deleting_an_upload_removes_its_file(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);

        $this->actingAs($admin);

        $id = $this->post('/bough/api/documents', [
            'kind' => 'upload', 'client_id' => $client->id, 'folder' => 'other',
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $path = ClientDocument::query()->findOrFail($id)->storage_path;

        $this->patchJson("/bough/api/documents/{$id}", ['title' => 'Kick-off notes', 'folder' => 'strategy'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Kick-off notes')
            ->assertJsonPath('data.folder', 'strategy');

        $this->deleteJson("/bough/api/documents/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('documents', ['id' => $id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('activity_log', ['action' => 'document.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('activity_log', ['action' => 'document.deleted', 'entity_id' => $id]);
    }

    public function test_private_documents_require_client_access_and_the_right_workspace(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace('agency-two');
        $client = $this->client($workspace);
        $foreignClient = $this->client($other, 'foreign-client');
        $writer = $this->member($workspace, UserRole::Writer);
        $admin = $this->member($workspace);

        $document = $this->uploadAs($admin, $client, 'bough');
        $foreign = $this->uploadAs($this->member($other), $foreignClient, 'agency-two');

        $this->actingAs($writer);
        $this->getJson("/bough/api/documents/{$document->id}")->assertForbidden();
        $this->get("/bough/api/documents/{$document->id}/download")->assertForbidden();
        $this->getJson('/bough/api/documents')->assertOk()->assertJsonPath('meta.total', 0);

        $this->grantClientAccess($writer, $client);
        $this->get("/bough/api/documents/{$document->id}/download")->assertOk();
        $this->deleteJson("/bough/api/documents/{$document->id}")->assertForbidden();

        $this->actingAs($admin);
        $this->getJson("/bough/api/documents/{$foreign->id}")->assertNotFound();
        $this->get("/bough/api/documents/{$foreign->id}/download")->assertNotFound();
        $this->deleteJson("/bough/api/documents/{$foreign->id}")->assertNotFound();
        $this->postJson('/bough/api/documents', [
            'kind' => 'link', 'client_id' => $foreignClient->id, 'folder' => 'other', 'title' => 'x', 'external_url' => 'https://example.com',
        ])->assertNotFound();

        auth()->logout();
        $this->get("/bough/api/documents/{$document->id}/download", ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_documents_linked_through_mcp_appear_in_the_client_library(): void
    {
        $workspace = $this->workspace();
        $client = $this->client($workspace);
        $admin = $this->member($workspace);

        app(DocumentService::class)->link($client, $admin, [
            'title' => 'Content plan',
            'external_url' => 'https://docs.google.com/document/d/xyz',
            'folder' => 'content',
            'document_type' => 'Plan',
        ], 'mcp', DocumentSource::GoogleDoc);

        $this->actingAs($admin)
            ->getJson("/bough/api/documents?client_id={$client->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', 'google_drive')
            ->assertJsonPath('data.0.google_doc_url', 'https://docs.google.com/document/d/xyz');
    }

    private function uploadAs(User $user, Client $client, string $slug): ClientDocument
    {
        $id = $this->actingAs($user)->post("/{$slug}/api/documents", [
            'kind' => 'upload', 'client_id' => $client->id, 'folder' => 'reports',
            'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        return ClientDocument::query()->findOrFail($id);
    }
}
