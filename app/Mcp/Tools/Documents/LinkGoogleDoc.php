<?php

namespace App\Mcp\Tools\Documents;

use App\Enums\DocumentFolder;
use App\Enums\DocumentSource;
use App\Mcp\Tools\Concerns\SerializesModels;
use App\Models\ClientDocument;
use App\Services\Clients\ClientService;
use App\Services\Documents\DocumentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('link_google_doc')]
#[Description('Link one Google document URL to one client document-library record. Folder is one of: seo, strategy, content, reports, other.')]
class LinkGoogleDoc extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, DocumentService $documents, TenantContext $tenant)
    {
        $data = Validator::make([...$request->all(), 'folder' => strtolower((string) $request->get('folder'))], [
            'client_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'google_doc_url' => ['required', 'url:http,https', 'max:2048'],
            'folder' => ['required', Rule::enum(DocumentFolder::class)],
            'document_type' => ['required', 'string', 'max:80'],
        ])->validate();

        $client = $clients->findInWorkspace($tenant->workspace(), (int) $data['client_id']);
        Gate::authorize('create', [ClientDocument::class, $client]);

        $document = $documents->link($client, $request->user(), [
            'title' => $data['title'],
            'external_url' => $data['google_doc_url'],
            'folder' => $data['folder'],
            'document_type' => $data['document_type'],
        ], 'mcp', DocumentSource::GoogleDoc);

        return Response::structured(['document' => $this->documentPayload($document->load('workspace:id,slug'))]);
    }
}
