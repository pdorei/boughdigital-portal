<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace?->id,
                'name' => $this->workspace?->name,
                'slug' => $this->workspace?->slug,
            ]),
            'workspaces' => $this->whenLoaded('clientAccess', function () {
                return collect([$this->workspace])
                    ->merge($this->clientAccess->pluck('workspace'))
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn ($workspace) => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                    ]);
            }),
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'avatar_url' => $this->avatar_url,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'datetime_format' => $this->datetime_format,
            'is_active' => $this->is_active,
            'last_login' => $this->last_login?->toISOString(),
            'client_ids' => $this->whenLoaded('clientAccess', fn () => $this->clientAccess->pluck('id')->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, function () use ($request) {
                $actor = $request->user();
                $isSelf = $actor->is($this->resource);

                return [
                    'update' => $actor->can('update', $this->resource),
                    'deactivate' => ! $isSelf && $actor->can('deactivate', $this->resource),
                    'remove_from_workspace' => ! $isSelf && $this->workspace_id !== null && $actor->can('removeFromWorkspace', $this->resource),
                    'delete' => ! $isSelf && ! $this->isSuperAdmin() && $actor->can('delete', $this->resource),
                    'change_credentials' => $actor->can('changeCredentials', $this->resource),
                ];
            }),
        ];
    }
}
