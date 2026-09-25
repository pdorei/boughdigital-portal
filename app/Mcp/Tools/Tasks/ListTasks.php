<?php

namespace App\Mcp\Tools\Tasks;

use App\Mcp\Tools\Concerns\SerializesModels;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_tasks')]
#[Description('List tasks visible to the authenticated user in the active workspace.')]
class ListTasks extends Tool
{
    use SerializesModels;

    public function handle(Request $request, TaskService $tasks, TenantContext $tenant)
    {
        $workspace = $tenant->workspace();
        $user = $request->user();
        abort_unless($user, 401);
        Gate::authorize('viewAny', [Task::class, $workspace]);

        $page = $tasks->list($workspace, $user, $request->all());

        return Response::structured([
            'tasks' => collect($page->items())->map(fn ($task) => $this->taskPayload($task))->values()->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage()],
        ]);
    }
}
