<?php

namespace App\Http\Controllers\Pages;

use App\Enums\UserRole;
use App\Http\Resources\Admin\WorkspaceResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\StorageSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\View\View;

/**
 * Every route using this controller sits behind the Super Admin gate in routes/web.php.
 */
class AdminPageController extends PortalPageController
{
    private const OVERVIEW_PAGES = ['dashboard', 'workspaces', 'system'];

    private const FILTERED_PAGES = ['users', 'clients', 'documents', 'tasks', 'activity', 'integrations', 'mcp'];

    public function page(string $page = 'dashboard'): View
    {
        $pageData = [];

        if (in_array($page, self::OVERVIEW_PAGES, true)) {
            $pageData += $this->overview();
        }

        if (in_array($page, self::FILTERED_PAGES, true)) {
            $pageData += $this->filters();
        }

        if ($page === 'settings') {
            $pageData['storage_settings'] = app(StorageSettingsService::class)->publicSettings();
        }

        return $this->render($page, 'admin', $pageData);
    }

    public function createWorkspace(): View
    {
        return $this->render('workspace-create', 'admin');
    }

    public function showWorkspace(Request $request, Workspace $workspace): View
    {
        return $this->render('workspace-show', 'admin', ['workspace' => $this->workspaceDetail($request, $workspace)]);
    }

    public function editWorkspace(Request $request, Workspace $workspace): View
    {
        return $this->render('workspace-edit', 'admin', ['workspace' => $this->workspaceDetail($request, $workspace)]);
    }

    public function createUser(): View
    {
        return $this->render('user-create', 'admin', $this->userFormData());
    }

    public function showUser(Request $request, User $user): View
    {
        return $this->render('user-show', 'admin', $this->userFormData($request, $user));
    }

    public function editUser(Request $request, User $user): View
    {
        return $this->render('user-edit', 'admin', $this->userFormData($request, $user));
    }

    private function overview(): array
    {
        $storageByWorkspace = ClientDocument::query()
            ->selectRaw('workspace_id, coalesce(sum(size_bytes), 0) as total_bytes')
            ->groupBy('workspace_id')
            ->pluck('total_bytes', 'workspace_id');

        $workspaces = Workspace::query()->withCount(['users', 'clients', 'documents', 'tasks'])->orderBy('name')->get();

        $stats = [
            ['label' => 'Total Workspaces', 'value' => $workspaces->count(), 'trend' => $workspaces->where('is_active', true)->count().' active'],
            ['label' => 'Total Users', 'value' => User::query()->count(), 'trend' => 'Across all roles'],
            ['label' => 'Total Clients', 'value' => Client::query()->count(), 'trend' => 'Managed accounts'],
            ['label' => 'Total Documents', 'value' => ClientDocument::query()->count(), 'trend' => 'Uploaded files and links'],
            ['label' => 'Total Tasks', 'value' => Task::query()->count(), 'trend' => 'Across all workspaces'],
            ['label' => 'Uploaded Storage', 'value' => Number::fileSize((int) $storageByWorkspace->sum()), 'trend' => 'Private client documents'],
            ['label' => 'Failed Jobs', 'value' => DB::table('failed_jobs')->count(), 'trend' => 'Queue failures'],
        ];

        return [
            'stats' => $stats,
            'workspaces' => $workspaces->map(fn (Workspace $workspace) => [
                ...(new WorkspaceResource($workspace))->resolve(),
                'documents_count' => $workspace->documents_count,
                'tasks_count' => $workspace->tasks_count,
                'storage' => Number::fileSize((int) ($storageByWorkspace[$workspace->id] ?? 0)),
            ])->all(),
            'recent_activity' => ActivityLog::query()
                ->with(['workspace:id,name', 'user:id,name'])
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (ActivityLog $activity) => [
                    'workspace' => $activity->workspace?->name ?? 'Platform',
                    'user' => $activity->user?->name,
                    'action' => $activity->action,
                    'source' => $activity->source,
                    'created_at' => $activity->created_at?->toISOString(),
                ])->all(),
            'integration_warnings' => DB::table('client_integrations')
                ->join('clients', 'client_integrations.client_id', '=', 'clients.id')
                ->join('workspaces', 'clients.workspace_id', '=', 'workspaces.id')
                ->whereIn('client_integrations.status', ['error', 'expired'])
                ->orderByDesc('client_integrations.updated_at')
                ->limit(6)
                ->get(['client_integrations.provider', 'client_integrations.status', 'client_integrations.updated_at', 'clients.name as client', 'workspaces.name as workspace'])
                ->all(),
        ];
    }

    private function filters(): array
    {
        return [
            'filter_workspaces' => Workspace::query()->orderBy('name')->get(['id', 'name', 'slug'])->toArray(),
            'filter_clients' => Client::query()->with('workspace:id,name')->orderBy('name')->get(['id', 'workspace_id', 'name', 'slug'])
                ->map(fn (Client $client) => [...$client->only(['id', 'workspace_id', 'name', 'slug']), 'workspace' => $client->workspace?->name])
                ->all(),
            'filter_users' => User::query()->where('role', '!=', UserRole::SuperAdmin)->orderBy('name')->get(['id', 'name', 'email'])->toArray(),
        ];
    }

    private function workspaceDetail(Request $request, Workspace $workspace): array
    {
        $workspace->loadCount(['users', 'clients', 'documents', 'tasks'])
            ->load([
                'users' => fn ($query) => $query->orderBy('name')->limit(8),
                'clients' => fn ($query) => $query->orderBy('name')->limit(8),
            ]);

        return [
            ...(new WorkspaceResource($workspace))->resolve($request),
            'documents_count' => $workspace->documents_count,
            'tasks_count' => $workspace->tasks_count,
            'users' => $workspace->users->map(fn (User $user) => [...$user->only(['id', 'name', 'email', 'is_active']), 'role' => $user->role->value])->all(),
            'clients' => $workspace->clients->map(fn (Client $client) => [...$client->only(['id', 'name', 'slug']), 'status' => $client->status->value])->all(),
        ];
    }

    private function userFormData(?Request $request = null, ?User $user = null): array
    {
        return [
            'selected_user' => $user ? (new UserResource($user->load(['workspace', 'clientAccess.workspace'])))->resolve($request) : null,
            'form_workspaces' => Workspace::query()->orderBy('name')->get(['id', 'name', 'slug'])->toArray(),
            'form_clients' => Client::query()->orderBy('name')->get(['id', 'workspace_id', 'name', 'slug'])->toArray(),
        ];
    }
}
