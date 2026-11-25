<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientAiConfigRequest;
use App\Http\Resources\ClientAiConfigResource;
use App\Models\Client;
use App\Models\ClientAiConfig;
use App\Services\Clients\ClientAiConfigService;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ClientAiConfigController extends Controller
{
    public function __construct(
        private ClientAiConfigService $configs,
        private ClientService $clients,
        private TenantContext $tenant,
    ) {}

    public function show(string $workspace, int $client): ClientAiConfigResource
    {
        $client = $this->authorizedClient('view', $client);

        return new ClientAiConfigResource($this->configs->forClient($client));
    }

    public function update(ClientAiConfigRequest $request, string $workspace, int $client): ClientAiConfigResource
    {
        $client = $this->authorizedClient('update', $client);

        return new ClientAiConfigResource($this->configs->save($client, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, string $workspace, int $client): ClientAiConfigResource
    {
        $client = $this->authorizedClient('delete', $client);
        $this->configs->reset($client, $request->user());

        return new ClientAiConfigResource($this->configs->forClient($client));
    }

    private function authorizedClient(string $ability, int $id): Client
    {
        $client = $this->clients->findInWorkspace($this->tenant->workspace(), $id);
        $this->authorize($ability, [ClientAiConfig::class, $client]);

        return $client;
    }
}
