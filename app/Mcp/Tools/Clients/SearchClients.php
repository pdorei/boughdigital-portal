<?php

namespace App\Mcp\Tools\Clients;

use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('search_clients')]
#[Description('Search clients visible to the authenticated user in the active workspace by name or website.')]
class SearchClients extends ListClients
{
    public function handle(Request $request, ClientService $clients, TenantContext $tenant)
    {
        $request->validate(['search' => ['required', 'string', 'max:255']]);

        return parent::handle($request, $clients, $tenant);
    }
}
