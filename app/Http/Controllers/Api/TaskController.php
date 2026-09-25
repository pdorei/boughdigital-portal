<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks, private TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('viewAny', [Task::class, $workspace]);

        $filters = $request->only(['search', 'status', 'priority', 'assigned_to', 'client_id', 'open', 'page', 'per_page']);

        return TaskResource::collection($this->tasks->list($workspace, $request->user(), $filters));
    }

    public function store(TaskRequest $request): TaskResource
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('create', [Task::class, $workspace]);

        return new TaskResource($this->tasks->create($workspace, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $task): TaskResource
    {
        $task = $this->tasks->findInWorkspace($this->tenant->workspace(), $task);
        $this->authorize('view', $task);

        return new TaskResource($task->load(['client:id,name,slug', 'assignee:id,name']));
    }

    public function update(TaskRequest $request, string $workspace, int $task): TaskResource
    {
        $task = $this->tasks->findInWorkspace($this->tenant->workspace(), $task);
        $this->authorize('update', $task);

        return new TaskResource($this->tasks->update($task, $request->user(), $request->validated()));
    }

    public function updateStatus(Request $request, string $workspace, int $task): TaskResource
    {
        $task = $this->tasks->findInWorkspace($this->tenant->workspace(), $task);
        $this->authorize('changeStatus', $task);

        $data = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);

        return new TaskResource($this->tasks->changeStatus($task, $request->user(), TaskStatus::from($data['status'])));
    }

    public function destroy(Request $request, string $workspace, int $task): Response
    {
        $task = $this->tasks->findInWorkspace($this->tenant->workspace(), $task);
        $this->authorize('delete', $task);

        $this->tasks->delete($task, $request->user());

        return response()->noContent();
    }
}
