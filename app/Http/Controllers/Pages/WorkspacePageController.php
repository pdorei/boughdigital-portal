<?php

namespace App\Http\Controllers\Pages;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Resources\ClientResource;
use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspacePageController extends PortalPageController
{
    /** Modules that are routed but belong to a later delivery phase. */
    private const PLANNED_PAGES = ['content', 'seo', 'outreach', 'reports'];

    public function __construct(private TenantContext $tenant, private ClientService $clients) {}

    public function dashboard(Request $request): View
    {
        $workspace = $this->tenant->workspace();
        $user = $request->user();

        return $this->page('dashboard', [
            'stats' => [
                'clients' => Client::query()->whereBelongsTo($workspace)->visibleTo($user)->count(),
                'open_tasks' => Task::query()->whereBelongsTo($workspace)->visibleTo($user)->where('status', '!=', TaskStatus::Completed)->count(),
                'my_open_tasks' => Task::query()->whereBelongsTo($workspace)->where('assigned_to', $user->id)->where('status', '!=', TaskStatus::Completed)->count(),
                'documents' => ClientDocument::query()->whereBelongsTo($workspace)->visibleTo($user)->count(),
            ],
        ]);
    }

    public function clients(): View
    {
        return $this->page('clients');
    }

    public function createClient(): View
    {
        $this->authorize('create', [Client::class, $this->tenant->workspace()]);

        return $this->page('client-create');
    }

    public function showClient(Request $request, string $workspace, string $client): View
    {
        $client = $this->clients->findBySlug($this->tenant->workspace(), $client);
        $this->authorize('view', $client);

        $client->loadCount([
            'documents',
            'tasks',
            'tasks as open_tasks_count' => fn ($query) => $query->where('status', '!=', TaskStatus::Completed),
        ]);

        return $this->page('client-detail', [
            'client' => (new ClientResource($client))->resolve($request),
            'team' => $client->members()->where('is_active', true)->orderBy('name')->get(['users.id', 'name', 'role'])
                ->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name, 'role' => $member->role->label()]),
            'form_options' => $this->taskFormOptions($request->user()),
        ]);
    }

    public function editClient(Request $request, string $workspace, string $client): View
    {
        $client = $this->clients->findBySlug($this->tenant->workspace(), $client);
        $this->authorize('update', $client);

        return $this->page('client-edit', ['client' => (new ClientResource($client))->resolve($request)]);
    }

    public function tasks(Request $request): View
    {
        return $this->page('tasks', ['form_options' => $this->taskFormOptions($request->user())]);
    }

    public function documents(Request $request): View
    {
        return $this->page('documents', ['form_options' => $this->taskFormOptions($request->user())]);
    }

    public function activity(): View
    {
        $this->authorize('viewActivity', $this->tenant->workspace());

        return $this->page('activity');
    }

    public function team(): View
    {
        $this->authorize('viewAny', [User::class, $this->tenant->workspace()]);

        return $this->page('team');
    }

    public function createMember(): View
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('create', [User::class, $workspace]);

        return $this->page('team-create', ['form_clients' => $this->clientOptions($workspace)]);
    }

    public function showMember(Request $request, string $workspace, User $user): View
    {
        return $this->memberPage($request, 'team-show', 'view', $user);
    }

    public function editMember(Request $request, string $workspace, User $user): View
    {
        return $this->memberPage($request, 'team-edit', 'update', $user);
    }

    public function settings(): View
    {
        return $this->page('settings');
    }

    public function profile(): View
    {
        return $this->page('profile');
    }

    public function planned(string $workspace, string $module): View
    {
        abort_unless(in_array($module, self::PLANNED_PAGES, true), 404);

        return $this->page($module);
    }

    private function memberPage(Request $request, string $page, string $ability, User $member): View
    {
        $workspace = $this->tenant->workspace();

        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $this->authorize($ability, $member);

        return $this->page($page, [
            'selected_user' => (new UserResource($member->load(['workspace', 'clientAccess'])))->resolve($request),
            'form_clients' => $this->clientOptions($workspace),
        ]);
    }

    private function page(string $page, array $pageData = []): View
    {
        return $this->render($page, 'workspace', $pageData, $this->tenant->workspace());
    }

    /**
     * Select options for task and document forms, limited to what the user may use.
     */
    private function taskFormOptions(User $user): array
    {
        $workspace = $this->tenant->workspace();

        return [
            'clients' => Client::query()->whereBelongsTo($workspace)->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client) => ['value' => $client->id, 'label' => $client->name]),
            'assignees' => User::query()->whereBelongsTo($workspace)->where('is_active', true)->where('role', '!=', UserRole::SuperAdmin)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $member) => ['value' => $member->id, 'label' => $member->name]),
        ];
    }

    private function clientOptions(Workspace $workspace): array
    {
        return Client::query()->whereBelongsTo($workspace)->orderBy('name')->get(['id', 'workspace_id', 'name', 'slug'])
            ->map(fn (Client $client) => $client->only(['id', 'workspace_id', 'name', 'slug']))
            ->all();
    }
}
