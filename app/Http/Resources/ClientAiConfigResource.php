<?php

namespace App\Http\Resources;

use App\Models\ClientAiConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientAiConfig */
class ClientAiConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'configured' => $this->exists,
            'brand_tone' => $this->brand_tone,
            'writing_preferences' => $this->writing_preferences,
            'industry_context' => $this->industry_context,
            // jsonb does not keep object key order, so each entry is rebuilt; list order is kept.
            'key_urls' => array_map(fn (array $entry) => ['label' => $entry['label'], 'url' => $entry['url']], $this->key_urls ?? []),
            'max_key_urls' => ClientAiConfig::MAX_KEY_URLS,
            'custom_instructions' => $this->custom_instructions,
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => $this->when($request->user() !== null, fn () => [
                'update' => $request->user()->can('update', [ClientAiConfig::class, $this->client]),
                'reset' => $request->user()->can('delete', [ClientAiConfig::class, $this->client]),
            ]),
        ];
    }
}
