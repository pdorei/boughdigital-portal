<?php

namespace App\Http\Controllers\Admin\Api\Upwork;

use App\Enums\UpworkJobStatus;
use App\Enums\UpworkJobType;
use App\Enums\UpworkTrack;
use App\Http\Controllers\Controller;
use App\Http\Resources\Upwork\UpworkJobResource;
use App\Models\UpworkJob;
use App\Services\Upwork\UpworkJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UpworkJobController extends Controller
{
    public function __construct(private UpworkJobService $jobs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage-upwork');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(UpworkJobStatus::class)],
            'track' => ['nullable', Rule::enum(UpworkTrack::class)],
            'job_type' => ['nullable', Rule::enum(UpworkJobType::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return UpworkJobResource::collection($this->jobs->list($filters));
    }

    public function show(int $upworkJob): UpworkJobResource
    {
        $this->authorize('manage-upwork');

        return new UpworkJobResource($this->jobs->find($upworkJob));
    }

    public function review(Request $request, UpworkJob $upworkJob): JsonResponse
    {
        $this->authorize('manage-upwork');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['yes', 'no'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $job = $this->jobs->review($upworkJob, $data['decision'] === 'yes', $request->user(), $data['reason'] ?? null);

        return response()->json([
            'data' => new UpworkJobResource($this->jobs->find($job->id)),
            'message' => $data['decision'] === 'yes' ? 'Job accepted. A proposal draft is being prepared.' : 'Job declined.',
        ]);
    }

    public function sendToReview(Request $request, UpworkJob $upworkJob): JsonResponse
    {
        $this->authorize('manage-upwork');

        $job = $this->jobs->sendToReview($upworkJob, $request->user());

        return response()->json(['data' => new UpworkJobResource($this->jobs->find($job->id)), 'message' => 'Job sent to review.']);
    }

    public function draftProposal(UpworkJob $upworkJob): JsonResponse
    {
        $this->authorize('manage-upwork');

        $this->jobs->redraft($upworkJob);

        return response()->json(['message' => 'A proposal draft is being prepared.'], 202);
    }
}
