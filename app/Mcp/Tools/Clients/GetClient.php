<?php

namespace App\Mcp\Tools\Clients;

use App\Mcp\Tools\Concerns\SerializesModels;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_client')]
#[Description('Get one client by ID after workspace and client-access checks.')]
class GetClient extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, TenantContext $tenant)
    {
        $data = $request->validate(['client_id' => ['required', 'integer']]);
        $client = $clients->findInWorkspace($tenant->workspace(), (int) $data['client_id']);
        Gate::authorize('view', $client);

        return Response::structured(['client' => $this->clientPayload($client)]);
    }
}
