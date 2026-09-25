<?php

namespace App\Http\Resources\Admin;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'primary_colour' => $this->primary_colour,
            'accent_colour' => $this->accent_colour,
            'settings' => $this->settings ?? [],
            'is_active' => $this->is_active,
            'users_count' => $this->whenCounted('users'),
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
