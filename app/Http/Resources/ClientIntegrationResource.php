<?php

namespace App\Http\Resources;

use App\Models\ClientIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes credentials, only whether one is configured.
 *
 * @mixin ClientIntegration
 */
class ClientIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'auth_type' => $this->auth_type->value,
            'auth_type_label' => $this->auth_type->label(),
            'status' => $this->status->value,
            'credential_configured' => $this->hasCredentials(),
            'available' => $this->provider->isAvailable(),
            'connected_at' => $this->connected_at?->toISOString(),
            'last_verified_at' => $this->last_verified_at?->toISOString(),
            'last_used_at' => $this->last_used_at?->toISOString(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ]),
        ];
    }
}
