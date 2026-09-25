<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkspaceRequest;
use App\Http\Requests\Admin\UpdateWorkspaceRequest;
use App\Http\Resources\Admin\WorkspaceResource;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceDeletionService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaces, private WorkspaceDeletionService $deletions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', Workspace::class);

        return WorkspaceResource::collection($this->workspaces->list($request->only(['search', 'status', 'sort', 'direction', 'page', 'per_page'])));
    }

    public function store(StoreWorkspaceRequest $request): WorkspaceResource
    {
        return new WorkspaceResource($this->workspaces->create($request->user(), $request->validated()));
    }

    public function show(Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        $workspace->loadCount(['users', 'clients']);

        return new WorkspaceResource($workspace);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        return new WorkspaceResource($this->workspaces->update($workspace, $request->user(), $request->validated())->loadCount(['users', 'clients']));
    }

    public function activate(Request $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);

        return new WorkspaceResource($this->workspaces->setActive($workspace, $request->user(), true)->loadCount(['users', 'clients']));
    }

    public function deactivate(Request $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);

        return new WorkspaceResource($this->workspaces->setActive($workspace, $request->user(), false)->loadCount(['users', 'clients']));
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);

        $data = $request->validate(['confirmation' => ['required', 'string']]);

        $this->deletions->delete($workspace, $request->user(), $data['confirmation']);

        return response()->json(['message' => 'Workspace deleted.']);
    }
}
