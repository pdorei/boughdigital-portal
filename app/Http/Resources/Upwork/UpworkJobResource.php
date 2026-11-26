<?php

namespace App\Http\Resources\Upwork;

use App\Models\UpworkJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The raw API payload is never returned; every field React needs is mapped.
 *
 * @mixin UpworkJob
 */
class UpworkJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('latestDecision') ? $this->latestDecision : null;
        $fit = $this->relationLoaded('decisions')
            ? $this->decisions->first(fn ($decision) => $decision->fit_score !== null)
            : null;

        return [
            'id' => $this->id,
            'upwork_job_id' => $this->upwork_job_id,
            'title' => $this->title,
            'description' => $this->when($request->routeIs('admin.api.upwork.jobs.show') || $request->boolean('with_description'), $this->description),
            'excerpt' => str($this->description ?? '')->squish()->limit(280)->toString(),
            'url' => $this->url,
            'track' => $this->track->value,
            'search_key' => $this->search_key?->value,
            'keyword' => $this->keyword,
            'job_type' => $this->job_type->value,
            'hourly_min' => $this->hourly_min !== null ? (float) $this->hourly_min : null,
            'hourly_max' => $this->hourly_max !== null ? (float) $this->hourly_max : null,
            'fixed_budget' => $this->fixed_budget !== null ? (float) $this->fixed_budget : null,
            'experience_level' => $this->experience_level,
            'duration' => $this->duration,
            'skills' => $this->skills ?? [],
            'proposals_count' => $this->proposals_count,
            'client' => [
                'country' => $this->client_country,
                'payment_verified' => $this->client_payment_verified,
                'total_spent' => $this->client_total_spent !== null ? (float) $this->client_total_spent : null,
                'hire_rate' => $this->client_hire_rate !== null ? (float) $this->client_hire_rate : null,
                'rating' => $this->client_rating !== null ? (float) $this->client_rating : null,
                'reviews_count' => $this->client_reviews_count,
            ],
            'screening_questions' => $this->screening_questions ?? [],
            'details_fetched_at' => $this->details_fetched_at?->toISOString(),
            'status' => $this->status->value,
            'posted_at' => $this->posted_at?->toISOString(),
            'first_seen_at' => $this->first_seen_at?->toISOString(),
            'fit_score' => ($fit ?? $latest)?->fit_score,
            'latest_decision' => $latest ? new UpworkDecisionResource($latest) : null,
            'decisions' => UpworkDecisionResource::collection($this->whenLoaded('decisions')),
            'proposal' => $this->whenLoaded('proposal', fn () => $this->proposal ? [
                'id' => $this->proposal->id,
                'status' => $this->proposal->status->value,
            ] : null),
        ];
    }
}
