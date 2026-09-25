<?php

namespace App\Models;

use App\Enums\DocumentFolder;
use App\Enums\DocumentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'client_id', 'title', 'document_type', 'folder', 'provider', 'external_id', 'external_url', 'storage_disk', 'storage_path', 'original_name', 'mime_type', 'size_bytes', 'metadata', 'created_by'])]
#[Hidden(['storage_disk', 'storage_path'])]
class ClientDocument extends Model
{
    use HasFactory;

    protected $table = 'documents';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUpload(): bool
    {
        return $this->provider === DocumentSource::Upload;
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->role->canAccessAllWorkspaceClients()) {
            return;
        }

        $query->whereIn('documents.client_id', $user->clientAccess()->select('clients.id'));
    }

    protected function casts(): array
    {
        return [
            'provider' => DocumentSource::class,
            'folder' => DocumentFolder::class,
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }
}
