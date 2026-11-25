<?php

namespace App\Http\Resources;

use App\Models\ClientCompetitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientCompetitor */
class ClientCompetitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'competitor_name' => $this->competitor_name,
            'competitor_domain' => $this->competitor_domain,
            'website_url' => "https://{$this->competitor_domain}",
            'competitor_type' => $this->competitor_type?->value,
            'tier' => $this->tier->value,
            'description' => $this->description,
            'industry' => $this->industry,
            'location' => $this->location,
            'notes' => $this->notes,
            'contact' => $this->keyed($this->contact, ClientCompetitor::CONTACT_FIELDS),
            'social_media' => $this->keyed($this->social_media, ClientCompetitor::SOCIAL_NETWORKS),
            'keywords' => $this->keywords ?? [],
            // The storage path is never exposed; the logo is served by an authorized route.
            'logo_url' => $this->hasLogo() ? route('workspace.api.clients.competitors.logo', [
                'workspace' => $this->client->workspace->slug,
                'client' => $this->client_id,
                'competitor' => $this->id,
                'v' => $this->updated_at?->timestamp,
            ], absolute: false) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ]),
        ];
    }

    /**
     * Always returns every known key so clients get a stable shape.
     */
    private function keyed(?array $values, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => $values[$key] ?? null])->all();
    }
}
