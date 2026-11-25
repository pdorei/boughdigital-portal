<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\IntegrationRequest;
use App\Http\Resources\ClientIntegrationResource;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Services\Clients\ClientService;
use App\Services\Integrations\ClientIntegrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientIntegrationController extends Controller
{
    public function __construct(
        private ClientIntegrationService $integrations,
        private ClientService $clients,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request, string $workspace, int $client): AnonymousResourceCollection
    {
        $client = $this->client($client);
        $this->authorize('viewAny', [ClientIntegration::class, $client]);

        return ClientIntegrationResource::collection($this->integrations->listForClient($client))->additional(['meta' => [
            'can_create' => $request->user()->can('create', [ClientIntegration::class, $client]),
        ]]);
    }

    public function store(IntegrationRequest $request, string $workspace, int $client): ClientIntegrationResource
    {
        $client = $this->client($client);
        $this->authorize('create', [ClientIntegration::class, $client]);

        return new ClientIntegrationResource($this->integrations->create($client, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $integration): ClientIntegrationResource
    {
        return new ClientIntegrationResource($this->integration('view', $client, $integration));
    }

    public function update(IntegrationRequest $request, string $workspace, int $client, int $integration): ClientIntegrationResource
    {
        $integration = $this->integration('update', $client, $integration);

        return new ClientIntegrationResource($this->integrations->update($integration, $request->user(), $request->validated()));
    }

    public function disconnect(Request $request, string $workspace, int $client, int $integration): ClientIntegrationResource
    {
        $integration = $this->integration('update', $client, $integration);

        return new ClientIntegrationResource($this->integrations->disconnect($integration, $request->user()));
    }

    public function destroy(Request $request, string $workspace, int $client, int $integration): Response
    {
        $this->integrations->delete($this->integration('delete', $client, $integration), $request->user());

        return response()->noContent();
    }

    /**
     * The client is resolved inside the current workspace, so integrations of
     * another workspace or client are simply not found.
     */
    private function client(int $id): Client
    {
        return $this->clients->findInWorkspace($this->tenant->workspace(), $id);
    }

    private function integration(string $ability, int $clientId, int $id): ClientIntegration
    {
        $integration = $this->integrations->findForClient($this->client($clientId), $id);
        $this->authorize($ability, $integration);

        return $integration;
    }
}
