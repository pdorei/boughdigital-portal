<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'slug', 'website_url', 'status', 'metadata'])]
class Client extends Model
{
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_client_access')->withTimestamps();
    }

    /**
     * Admins see every client in the workspace; everyone else only the
     * clients they were explicitly given access to.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->role->canAccessAllWorkspaceClients()) {
            return;
        }

        $query->whereIn('clients.id', $user->clientAccess()->select('clients.id'));
    }

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'metadata' => 'array',
        ];
    }
}
