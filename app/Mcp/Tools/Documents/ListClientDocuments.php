<?php

namespace App\Mcp\Tools\Documents;

use App\Mcp\Tools\Concerns\SerializesModels;
use App\Services\Clients\ClientService;
use App\Services\Documents\DocumentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_client_documents')]
#[Description('List documents for a client visible in the active workspace.')]
class ListClientDocuments extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, DocumentService $documents, TenantContext $tenant)
    {
        $data = $request->validate(['client_id' => ['required', 'integer']]);
        $client = $clients->findInWorkspace($tenant->workspace(), (int) $data['client_id']);
        Gate::authorize('view', $client);

        return Response::structured([
            'documents' => $documents->listForClient($client)->map(fn ($document) => $this->documentPayload($document))->values()->all(),
        ]);
    }
}
