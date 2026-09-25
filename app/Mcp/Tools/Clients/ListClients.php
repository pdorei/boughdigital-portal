<?php

namespace App\Mcp\Tools\Clients;

use App\Mcp\Tools\Concerns\SerializesModels;
use App\Models\Client;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_clients')]
#[Description('List clients visible to the authenticated user in the active workspace.')]
class ListClients extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, TenantContext $tenant)
    {
        $workspace = $tenant->workspace();
        $user = $request->user();
        abort_unless($user, 401);
        Gate::authorize('viewAny', [Client::class, $workspace]);

        $page = $clients->list($workspace, $user, $request->all());

        return Response::structured([
            'clients' => collect($page->items())->map(fn ($client) => $this->clientPayload($client))->values()->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage()],
        ]);
    }
}
