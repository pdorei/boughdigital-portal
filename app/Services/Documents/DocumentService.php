<?php

namespace App\Services\Documents;

use App\Enums\ActivityEvent;
use App\Enums\DocumentFolder;
use App\Enums\DocumentSource;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use App\Services\Workspaces\WorkspaceStorageService;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentService
{
    public function __construct(private ActivityLogger $activity, private WorkspaceStorageService $storage) {}

    public function list(Workspace $workspace, User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $folder = DocumentFolder::tryFrom((string) ($filters['folder'] ?? ''));
        $source = DocumentSource::tryFrom((string) ($filters['source'] ?? ''));

        $query = ClientDocument::query()
            ->with(['workspace:id,slug', 'client:id,name,slug', 'creator:id,name'])
            ->whereBelongsTo($workspace)
            ->visibleTo($user)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('original_name', "%{$search}%")
                ->orWhereLike('document_type', "%{$search}%")))
            ->when($folder, fn ($query) => $query->where('folder', $folder))
            ->when($source, fn ($query) => $query->where('provider', $source))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', (int) $clientId))
            ->latest();

        return Pagination::paginate($query, $filters);
    }

    public function listForClient(Client $client): Collection
    {
        return $client->documents()->with('workspace:id,slug')->orderBy('folder')->orderBy('title')->get();
    }

    public function findInWorkspace(Workspace $workspace, int $id): ClientDocument
    {
        return ClientDocument::query()->whereBelongsTo($workspace)->findOrFail($id);
    }

    public function upload(Client $client, User $user, UploadedFile $file, array $data, string $source = 'portal'): ClientDocument
    {
        $stored = $this->storage->storeClientDocument($client, $file);

        try {
            $document = ClientDocument::query()->create([
                ...$this->metadata($data),
                'workspace_id' => $client->workspace_id,
                'client_id' => $client->id,
                'title' => $data['title'] ?? pathinfo($this->displayName($file), PATHINFO_FILENAME),
                'provider' => DocumentSource::Upload,
                'storage_disk' => $stored['disk'],
                'storage_path' => $stored['path'],
                'original_name' => $this->displayName($file),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'created_by' => $user->id,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($stored['disk'], $stored['path']);

            throw $exception;
        }

        $this->activity->log(ActivityEvent::DocumentUploaded, $client->workspace, $user, $document, [
            'title' => $document->title,
            'folder' => $document->folder->value,
            'size_bytes' => $document->size_bytes,
        ], $source);

        return $document;
    }

    /**
     * Stores a reference to a document that lives elsewhere. Google Doc links
     * are only recognised by their host; no Google API is called.
     */
    public function link(Client $client, User $user, array $data, string $source = 'portal', ?DocumentSource $as = null): ClientDocument
    {
        $document = ClientDocument::query()->create([
            ...$this->metadata($data),
            'workspace_id' => $client->workspace_id,
            'client_id' => $client->id,
            'title' => $data['title'],
            'provider' => $as ?? $this->linkSource($data['external_url']),
            'external_url' => $data['external_url'],
            'created_by' => $user->id,
        ]);

        $this->activity->log(ActivityEvent::DocumentLinked, $client->workspace, $user, $document, [
            'title' => $document->title,
            'folder' => $document->folder->value,
            'source' => $document->provider->value,
        ], $source);

        return $document;
    }

    public function update(ClientDocument $document, User $user, array $data, string $source = 'portal'): ClientDocument
    {
        $fields = $document->isUpload() ? ['title', 'folder', 'document_type'] : ['title', 'folder', 'document_type', 'external_url'];

        $document->fill(Arr::only($data, $fields));
        $changed = array_keys($document->getDirty());
        $document->save();

        $this->activity->log(ActivityEvent::DocumentUpdated, $document->workspace, $user, $document, [
            'title' => $document->title,
            'fields' => $changed,
        ], $source);

        return $document;
    }

    public function delete(ClientDocument $document, User $user, string $source = 'portal'): void
    {
        DB::transaction(function () use ($document, $user, $source): void {
            $this->activity->log(ActivityEvent::DocumentDeleted, $document->workspace, $user, $document, [
                'title' => $document->title,
                'source' => $document->provider->value,
            ], $source);

            $document->delete();
        });

        // The record is gone first so the library never lists a missing file;
        // a failed file removal is logged by the storage service.
        if ($document->isUpload()) {
            $this->storage->delete($document->storage_disk, $document->storage_path);
        }
    }

    public function download(ClientDocument $document): Response
    {
        return $this->storage->download($document);
    }

    private function metadata(array $data): array
    {
        return [
            'folder' => $data['folder'] ?? DocumentFolder::Other,
            'document_type' => $data['document_type'] ?? 'General',
            'metadata' => [],
        ];
    }

    private function linkSource(string $url): DocumentSource
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['docs.google.com', 'drive.google.com'], true)
            ? DocumentSource::GoogleDoc
            : DocumentSource::ExternalLink;
    }

    /**
     * The browser-supplied name is only kept for display and download headers.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($file->getClientOriginalName())) ?: 'document';

        return Str::limit($name, 200, '');
    }
}
