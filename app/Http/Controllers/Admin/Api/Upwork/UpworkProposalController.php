<?php

namespace App\Http\Controllers\Admin\Api\Upwork;

use App\Enums\UpworkProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upwork\UpdateProposalRequest;
use App\Http\Resources\Upwork\UpworkProposalResource;
use App\Models\UpworkProposal;
use App\Services\Upwork\UpworkProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Approve, edit, reject and mark sent. There is no submit endpoint: proposals
 * are pasted into Upwork by a person.
 */
class UpworkProposalController extends Controller
{
    public function __construct(private UpworkProposalService $proposals) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage-upwork');

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'all', ...array_column(UpworkProposalStatus::cases(), 'value')])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return UpworkProposalResource::collection($this->proposals->list($filters));
    }

    public function show(int $upworkProposal): UpworkProposalResource
    {
        $this->authorize('manage-upwork');

        return new UpworkProposalResource($this->proposals->find($upworkProposal));
    }

    public function update(UpdateProposalRequest $request, UpworkProposal $upworkProposal): JsonResponse
    {
        $this->authorize('manage-upwork');

        $proposal = $this->proposals->update($upworkProposal, $request->validated(), $request->user());

        return $this->respond($proposal, 'Proposal saved.');
    }

    public function approve(Request $request, UpworkProposal $upworkProposal): JsonResponse
    {
        $this->authorize('manage-upwork');

        return $this->respond($this->proposals->approve($upworkProposal, $request->user()), 'Proposal approved.');
    }

    public function reject(Request $request, UpworkProposal $upworkProposal): JsonResponse
    {
        $this->authorize('manage-upwork');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->respond($this->proposals->reject($upworkProposal, $request->user(), $data['reason'] ?? null), 'Proposal rejected.');
    }

    public function markSent(Request $request, UpworkProposal $upworkProposal): JsonResponse
    {
        $this->authorize('manage-upwork');

        $data = $request->validate(['connects_spent' => ['nullable', 'integer', 'min:0', 'max:1000']]);

        return $this->respond($this->proposals->markSent($upworkProposal, $request->user(), $data['connects_spent'] ?? null), 'Proposal marked as sent.');
    }

    private function respond(UpworkProposal $proposal, string $message): JsonResponse
    {
        return response()->json([
            'data' => new UpworkProposalResource($this->proposals->find($proposal->id)),
            'message' => $message,
        ]);
    }
}
