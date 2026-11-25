<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;

/**
 * Looks the client up inside the resolved workspace, so a client ID from
 * another workspace is simply not found. Never trust a client ID on its own.
 */
trait ResolvesWorkspaceClient
{
    protected function workspaceClient(int $id): Client
    {
        return app(ClientService::class)->findInWorkspace(app(TenantContext::class)->workspace(), $id);
    }
}
