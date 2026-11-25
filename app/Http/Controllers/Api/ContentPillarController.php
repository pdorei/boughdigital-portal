<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesWorkspaceClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ContentPillarRequest;
use App\Http\Resources\ContentPillarResource;
use App\Models\ContentPillar;
use App\Services\Content\ContentPillarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContentPillarController extends Controller
{
    use ResolvesWorkspaceClient;

    public function __construct(private ContentPillarService $pillars) {}

    public function index(Request $request, string $workspace, int $client): AnonymousResourceCollection
    {
        $client = $this->workspaceClient($client);
        $this->authorize('viewAny', [ContentPillar::class, $client]);

        return ContentPillarResource::collection($this->pillars->listForClient($client))->additional(['meta' => [
            'can_create' => $request->user()->can('create', [ContentPillar::class, $client]),
        ]]);
    }

    public function store(ContentPillarRequest $request, string $workspace, int $client): ContentPillarResource
    {
        $client = $this->workspaceClient($client);
        $this->authorize('create', [ContentPillar::class, $client]);

        return new ContentPillarResource($this->pillars->create($client, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $pillar): ContentPillarResource
    {
        $client = $this->workspaceClient($client);
        $this->authorize('viewAny', [ContentPillar::class, $client]);

        return new ContentPillarResource($this->pillars->findForClient($client, $pillar)->loadCount(['articles', 'calendarEntries']));
    }

    public function update(ContentPillarRequest $request, string $workspace, int $client, int $pillar): ContentPillarResource
    {
        $pillar = $this->pillar('update', $client, $pillar);

        return new ContentPillarResource($this->pillars->update($pillar, $request->user(), $request->validated())->loadCount(['articles', 'calendarEntries']));
    }

    public function destroy(Request $request, string $workspace, int $client, int $pillar): Response
    {
        $this->pillars->delete($this->pillar('delete', $client, $pillar), $request->user());

        return response()->noContent();
    }

    private function pillar(string $ability, int $clientId, int $id): ContentPillar
    {
        $pillar = $this->pillars->findForClient($this->workspaceClient($clientId), $id);
        $this->authorize($ability, $pillar);

        return $pillar;
    }
}
