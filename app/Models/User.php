<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['workspace_id', 'name', 'email', 'password', 'role', 'avatar_url', 'timezone', 'date_format', 'datetime_format', 'is_active', 'last_login'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function clientAccess(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'user_client_access')
            ->withPivot('workspace_id')
            ->withTimestamps();
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin);
    }

    public function belongsToWorkspace(Workspace|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this->isSuperAdmin() || (int) $this->workspace_id === (int) $workspaceId;
    }

    public function canAccessClient(int $clientId): bool
    {
        return $this->role->canAccessAllWorkspaceClients()
            || $this->clientAccess()->whereKey($clientId)->exists();
    }

    /**
     * The workspace a non-platform user lands in after signing in, if it is usable.
     */
    public function activeWorkspace(): ?Workspace
    {
        $workspace = $this->workspace;

        return $workspace?->is_active ? $workspace : null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login' => 'datetime',
        ];
    }
}
