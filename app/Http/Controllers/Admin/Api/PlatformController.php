<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);

        $clients = Client::query()
            ->with('workspace:id,name,slug')
            ->withCount(['documents', 'tasks'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('website_url', 'like', "%{$filters['search']}%");
            }))
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate($filters['per_page']);

        return response()->json($this->paginated($clients, fn (Client $client) => [
            'id' => $client->id,
            'workspace_id' => $client->workspace_id,
            'workspace' => $this->workspaceSummary($client->workspace),
            'name' => $client->name,
            'slug' => $client->slug,
            'website_url' => $client->website_url,
            'status' => $client->status,
            'documents_count' => $client->documents_count,
            'tasks_count' => $client->tasks_count,
            'created_at' => $client->created_at?->toISOString(),
            'updated_at' => $client->updated_at?->toISOString(),
        ]));
    }

    public function documents(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);

        $documents = ClientDocument::query()
            ->with(['workspace:id,name,slug', 'client:id,workspace_id,name,slug', 'creator:id,name,email'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('document_type', 'like', "%{$filters['search']}%")
                    ->orWhere('folder', 'like', "%{$filters['search']}%");
            }))
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when($filters['client_id'], fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->filled('storage_disk'), function (Builder $query) use ($request): void {
                $disk = $request->string('storage_disk')->toString();
                $disk === 'none' ? $query->whereNull('storage_disk') : $query->where('storage_disk', $disk);
            })
            ->latest('created_at')
            ->paginate($filters['per_page']);

        return response()->json($this->paginated($documents, fn (ClientDocument $document) => [
            'id' => $document->id,
            'workspace_id' => $document->workspace_id,
            'workspace' => $this->workspaceSummary($document->workspace),
            'client' => $this->clientSummary($document->client),
            'title' => $document->title,
            'document_type' => $document->document_type,
            'folder' => $document->folder,
            'provider' => $document->provider,
            'storage_disk' => $document->storage_disk,
            'original_name' => $document->original_name,
            'size_bytes' => $document->size_bytes,
            'created_by' => $document->creator ? ['id' => $document->creator->id, 'name' => $document->creator->name, 'email' => $document->creator->email] : null,
            'external_url' => $document->external_url,
            'download_url' => $document->isUpload() && $document->workspace
                ? route('workspace.api.documents.download', ['workspace' => $document->workspace->slug, 'document' => $document->id], absolute: false)
                : null,
            'created_at' => $document->created_at?->toISOString(),
        ]));
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);

        $tasks = Task::query()
            ->with(['workspace:id,name,slug', 'client:id,workspace_id,name,slug', 'assignee:id,name,email', 'creator:id,name,email'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            }))
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when($filters['client_id'], fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->filled('assigned_to'), fn (Builder $query) => $query->where('assigned_to', (int) $request->input('assigned_to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderByRaw('due_date is null, due_date asc')
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page']);

        return response()->json($this->paginated($tasks, fn (Task $task) => [
            'id' => $task->id,
            'workspace_id' => $task->workspace_id,
            'workspace' => $this->workspaceSummary($task->workspace),
            'client' => $this->clientSummary($task->client),
            'title' => $task->title,
            'status' => $task->status?->value ?? $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'assigned_to' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name, 'email' => $task->assignee->email] : null,
            'created_by' => $task->creator ? ['id' => $task->creator->id, 'name' => $task->creator->name, 'email' => $task->creator->email] : null,
            'updated_at' => $task->updated_at?->toISOString(),
        ]));
    }

    public function activity(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);

        $activity = ActivityLog::query()
            ->with(['workspace:id,name,slug', 'user:id,name,email', 'client:id,name,slug'])
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('user_id', (int) $request->input('user_id')))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where('action', 'like', "%{$filters['search']}%"))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', 'like', '%'.$request->string('action')->toString().'%'))
            ->latest('created_at')
            ->paginate($filters['per_page']);

        return response()->json($this->paginated($activity, fn (ActivityLog $activity) => [
            'id' => $activity->id,
            'workspace' => $this->workspaceSummary($activity->workspace),
            'user' => $activity->user ? ['id' => $activity->user->id, 'name' => $activity->user->name, 'email' => $activity->user->email] : null,
            'client' => $this->clientSummary($activity->client),
            'action' => $activity->action,
            'entity_type' => $activity->entity_type,
            'entity_id' => $activity->entity_id,
            'source' => $activity->source,
            'ip_address' => $activity->ip_address,
            'created_at' => $activity->created_at?->toISOString(),
        ]));
    }

    public function integrations(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);

        $integrations = DB::table('client_integrations')
            ->join('clients', 'client_integrations.client_id', '=', 'clients.id')
            ->join('workspaces', 'clients.workspace_id', '=', 'workspaces.id')
            ->select([
                'client_integrations.id',
                'client_integrations.provider',
                'client_integrations.status',
                'client_integrations.last_verified_at',
                'client_integrations.updated_at',
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.slug as client_slug',
                'workspaces.id as workspace_id',
                'workspaces.name as workspace_name',
                'workspaces.slug as workspace_slug',
            ])
            ->when($filters['workspace_id'], fn ($query, int $workspaceId) => $query->where('clients.workspace_id', $workspaceId))
            ->when($filters['client_id'], fn ($query, int $clientId) => $query->where('clients.id', $clientId))
            ->when($request->filled('status'), fn ($query) => $query->where('client_integrations.status', $request->string('status')->toString()))
            ->when($filters['search'] !== '', fn ($query) => $query->where('client_integrations.provider', 'like', "%{$filters['search']}%"))
            ->orderBy('client_integrations.updated_at', 'desc')
            ->paginate($filters['per_page']);

        return response()->json($this->paginated($integrations, fn (object $integration) => [
            'id' => $integration->id,
            'workspace' => ['id' => $integration->workspace_id, 'name' => $integration->workspace_name, 'slug' => $integration->workspace_slug],
            'client' => ['id' => $integration->client_id, 'name' => $integration->client_name, 'slug' => $integration->client_slug],
            'provider' => $integration->provider,
            'status' => $integration->status,
            'last_verified_at' => $integration->last_verified_at,
            'updated_at' => $integration->updated_at,
        ]));
    }

    public function mcp(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);
        $filters = $this->filters($request);
        $toolNames = ['list_clients', 'search_clients', 'get_client', 'create_client', 'update_client', 'list_client_documents', 'link_google_doc', 'list_tasks'];

        $workspaces = Workspace::query()
            ->withCount(['users', 'clients'])
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId) => $query->whereKey($workspaceId))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where('name', 'like', "%{$filters['search']}%"))
            ->orderBy('name')
            ->paginate($filters['per_page']);

        $activityByWorkspace = ActivityLog::query()
            ->where(function (Builder $query): void {
                $query->where('source', 'mcp')->orWhere('action', 'like', 'mcp.%');
            })
            ->whereIn('workspace_id', $workspaces->getCollection()->pluck('id'))
            ->latest('created_at')
            ->get()
            ->groupBy('workspace_id');

        return response()->json($this->paginated($workspaces, fn (Workspace $workspace) => [
            'id' => $workspace->id,
            'workspace' => $this->workspaceSummary($workspace),
            'status' => $workspace->is_active ? 'active' : 'disabled',
            'tools' => $toolNames,
            'users_count' => $workspace->users_count,
            'clients_count' => $workspace->clients_count,
            'last_activity' => $activityByWorkspace->get($workspace->id)?->first()?->created_at?->toISOString(),
            'errors_count' => $activityByWorkspace->get($workspace->id)?->filter(fn (ActivityLog $log) => Str::contains($log->action, ['error', 'failed']))->count() ?? 0,
        ]));
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim($request->string('search')->toString()),
            'workspace_id' => $request->filled('workspace_id') ? (int) $request->input('workspace_id') : null,
            'client_id' => $request->filled('client_id') ? (int) $request->input('client_id') : null,
            'per_page' => min(100, max(1, (int) $request->input('per_page', 15))),
        ];
    }

    private function paginated($paginator, callable $mapper): array
    {
        return [
            'data' => $paginator->getCollection()->map($mapper)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    private function workspaceSummary(?Workspace $workspace): ?array
    {
        return $workspace ? ['id' => $workspace->id, 'name' => $workspace->name, 'slug' => $workspace->slug] : null;
    }

    private function clientSummary(?Client $client): ?array
    {
        return $client ? ['id' => $client->id, 'name' => $client->name, 'slug' => $client->slug] : null;
    }
}
