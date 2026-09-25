<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\ClientDocumentResource;
use App\Models\ClientDocument;
use App\Services\Clients\ClientService;
use App\Services\Documents\DocumentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documents,
        private ClientService $clients,
        private TenantContext $tenant,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('viewAny', [ClientDocument::class, $workspace]);

        $filters = $request->only(['search', 'client_id', 'folder', 'source', 'page', 'per_page']);

        return ClientDocumentResource::collection($this->documents->list($workspace, $request->user(), $filters));
    }

    public function store(StoreDocumentRequest $request): ClientDocumentResource
    {
        $client = $this->clients->findInWorkspace($this->tenant->workspace(), $request->integer('client_id'));
        $this->authorize('create', [ClientDocument::class, $client]);

        $data = $request->validated();

        $document = $data['kind'] === 'upload'
            ? $this->documents->upload($client, $request->user(), $request->file('file'), $data)
            : $this->documents->link($client, $request->user(), $data);

        return new ClientDocumentResource($document->load(['workspace:id,slug', 'client:id,name,slug', 'creator:id,name']));
    }

    public function show(string $workspace, int $document): ClientDocumentResource
    {
        $document = $this->findAuthorized('view', $document);

        return new ClientDocumentResource($document->load(['workspace:id,slug', 'client:id,name,slug', 'creator:id,name']));
    }

    public function update(UpdateDocumentRequest $request, string $workspace, int $document): ClientDocumentResource
    {
        $document = $this->findAuthorized('update', $document);

        return new ClientDocumentResource(
            $this->documents->update($document, $request->user(), $request->validated())->load(['workspace:id,slug', 'client:id,name,slug', 'creator:id,name'])
        );
    }

    public function destroy(Request $request, string $workspace, int $document): Response
    {
        $this->documents->delete($this->findAuthorized('delete', $document), $request->user());

        return response()->noContent();
    }

    public function download(string $workspace, int $document): FileResponse
    {
        $document = $this->findAuthorized('view', $document);

        abort_unless($document->isUpload(), 404);

        return $this->documents->download($document);
    }

    private function findAuthorized(string $ability, int $id): ClientDocument
    {
        $document = $this->documents->findInWorkspace($this->tenant->workspace(), $id);
        $this->authorize($ability, $document);

        return $document;
    }
}
