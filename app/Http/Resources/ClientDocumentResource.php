<?php

namespace App\Http\Resources;

use App\Enums\DocumentSource;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storage disks and paths never leave the server; uploads are reached through
 * the authorized download route so local and S3 storage look identical here.
 *
 * @mixin ClientDocument
 */
class ClientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $downloadUrl = $this->isUpload()
            ? route('workspace.api.documents.download', ['workspace' => $this->workspace->slug, 'document' => $this->id], absolute: false)
            : null;

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'document_type' => $this->document_type,
            'folder' => $this->folder->value,
            'source' => $this->provider->value,
            'provider' => $this->provider->value,
            'external_url' => $this->external_url,
            'google_doc_url' => $this->provider === DocumentSource::GoogleDoc ? $this->external_url : null,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'download_url' => $downloadUrl,
            'open_url' => $downloadUrl ?? $this->external_url,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'slug' => $this->client->slug,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ]),
        ];
    }
}
