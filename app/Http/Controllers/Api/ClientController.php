<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients, private TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('viewAny', [Client::class, $workspace]);

        return ClientResource::collection($this->clients->list($workspace, $request->user(), $request->only(['search', 'status', 'page', 'per_page'])));
    }

    public function store(ClientRequest $request): ClientResource
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('create', [Client::class, $workspace]);

        return new ClientResource($this->clients->create($workspace, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client): ClientResource
    {
        $client = $this->clients->findInWorkspace($this->tenant->workspace(), $client);
        $this->authorize('view', $client);

        return new ClientResource($client);
    }

    public function update(ClientRequest $request, string $workspace, int $client): ClientResource
    {
        $client = $this->clients->findInWorkspace($this->tenant->workspace(), $client);
        $this->authorize('update', $client);

        return new ClientResource($this->clients->update($client, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, string $workspace, int $client): Response
    {
        $client = $this->clients->findInWorkspace($this->tenant->workspace(), $client);
        $this->authorize('delete', $client);

        $this->clients->delete($client, $request->user());

        return response()->noContent();
    }
}
