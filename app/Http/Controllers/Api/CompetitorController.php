<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competitors\CompetitorLogoRequest;
use App\Http\Requests\Competitors\CompetitorRequest;
use App\Http\Resources\ClientCompetitorResource;
use App\Models\Client;
use App\Models\ClientCompetitor;
use App\Services\Clients\ClientService;
use App\Services\Competitors\CompetitorService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

class CompetitorController extends Controller
{
    public function __construct(
        private CompetitorService $competitors,
        private ClientService $clients,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request, string $workspace, int $client): AnonymousResourceCollection
    {
        $client = $this->client($client);
        $this->authorize('viewAny', [ClientCompetitor::class, $client]);

        return ClientCompetitorResource::collection($this->competitors->listForClient($client))->additional(['meta' => [
            'limit' => CompetitorService::MAX_PER_CLIENT,
            'max_keywords' => ClientCompetitor::MAX_KEYWORDS,
            'can_create' => $request->user()->can('create', [ClientCompetitor::class, $client]),
        ]]);
    }

    public function store(CompetitorRequest $request, string $workspace, int $client): ClientCompetitorResource
    {
        $client = $this->client($client);
        $this->authorize('create', [ClientCompetitor::class, $client]);

        return new ClientCompetitorResource($this->competitors->create($client, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $competitor): ClientCompetitorResource
    {
        return new ClientCompetitorResource($this->competitor('view', $client, $competitor));
    }

    public function update(CompetitorRequest $request, string $workspace, int $client, int $competitor): ClientCompetitorResource
    {
        $competitor = $this->competitor('update', $client, $competitor);

        return new ClientCompetitorResource($this->competitors->update($competitor, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, string $workspace, int $client, int $competitor): Response
    {
        $this->competitors->delete($this->competitor('delete', $client, $competitor), $request->user());

        return response()->noContent();
    }

    public function logo(string $workspace, int $client, int $competitor): FileResponse
    {
        return $this->competitors->logo($this->competitor('view', $client, $competitor));
    }

    public function uploadLogo(CompetitorLogoRequest $request, string $workspace, int $client, int $competitor): ClientCompetitorResource
    {
        $competitor = $this->competitor('update', $client, $competitor);

        return new ClientCompetitorResource($this->competitors->replaceLogo($competitor, $request->user(), $request->file('logo')));
    }

    public function removeLogo(Request $request, string $workspace, int $client, int $competitor): ClientCompetitorResource
    {
        $competitor = $this->competitor('update', $client, $competitor);

        return new ClientCompetitorResource($this->competitors->removeLogo($competitor, $request->user()));
    }

    /**
     * Resolving the client inside the current workspace first means a
     * competitor from another workspace or client is simply not found.
     */
    private function client(int $id): Client
    {
        return $this->clients->findInWorkspace($this->tenant->workspace(), $id);
    }

    private function competitor(string $ability, int $clientId, int $id): ClientCompetitor
    {
        $competitor = $this->competitors->findForClient($this->client($clientId), $id);
        $this->authorize($ability, $competitor);

        return $competitor;
    }
}
