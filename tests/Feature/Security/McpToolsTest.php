<?php

namespace Tests\Feature\Security;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_workspace_mcp_tools_function_through_http_transport(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Bough Digital', 'slug' => 'bough']);
        $admin = User::factory()->create(['workspace_id' => $workspace->id, 'role' => UserRole::Admin]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Acorn Legal',
            'slug' => 'acorn-legal',
            'website_url' => 'https://example.com',
        ]);
        Task::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'title' => 'Review technical SEO',
            'status' => TaskStatus::Backlog,
            'source' => 'manual',
        ]);

        Sanctum::actingAs($admin);

        $toolNames = collect($this->mcp($workspace, 'tools/list')['result']['tools'])->pluck('name')->all();

        $this->assertContains('list_clients', $toolNames);
        $this->assertContains('search_clients', $toolNames);
        $this->assertContains('get_client', $toolNames);
        $this->assertContains('create_client', $toolNames);
        $this->assertContains('update_client', $toolNames);
        $this->assertContains('list_client_documents', $toolNames);
        $this->assertContains('link_google_doc', $toolNames);
        $this->assertContains('list_tasks', $toolNames);

        $this->assertCount(1, $this->content($this->mcp($workspace, 'tools/call', ['name' => 'list_clients', 'arguments' => []]))['clients']);
        $this->assertCount(1, $this->content($this->mcp($workspace, 'tools/call', ['name' => 'search_clients', 'arguments' => ['search' => 'Acorn']]))['clients']);
        $this->assertSame($client->id, $this->content($this->mcp($workspace, 'tools/call', ['name' => 'get_client', 'arguments' => ['client_id' => $client->id]]))['client']['id']);

        $created = $this->content($this->mcp($workspace, 'tools/call', [
            'name' => 'create_client',
            'arguments' => ['name' => 'Canopy Health', 'website_url' => 'https://canopy.example'],
        ]))['client'];
        $this->assertSame('Canopy Health', $created['name']);

        $updated = $this->content($this->mcp($workspace, 'tools/call', [
            'name' => 'update_client',
            'arguments' => ['client_id' => $created['id'], 'status' => 'paused'],
        ]))['client'];
        $this->assertSame('paused', $updated['status']);

        $document = $this->content($this->mcp($workspace, 'tools/call', [
            'name' => 'link_google_doc',
            'arguments' => [
                'client_id' => $client->id,
                'title' => 'SEO Strategy',
                'google_doc_url' => 'https://docs.google.com/document/d/example',
                'folder' => 'SEO',
                'document_type' => 'strategy',
            ],
        ]))['document'];
        $this->assertSame('google_drive', $document['provider']);

        $this->assertCount(1, $this->content($this->mcp($workspace, 'tools/call', ['name' => 'list_client_documents', 'arguments' => ['client_id' => $client->id]]))['documents']);
        $this->assertCount(1, $this->content($this->mcp($workspace, 'tools/call', ['name' => 'list_tasks', 'arguments' => []]))['tasks']);
    }

    private function mcp(Workspace $workspace, string $method, array $params = []): array
    {
        return $this->postJson("/mcp/{$workspace->slug}", [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ])->assertOk()->json();
    }

    private function content(array $response): array
    {
        return json_decode($response['result']['content'][0]['text'], true);
    }
}
