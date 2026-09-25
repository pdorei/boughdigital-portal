<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private UserService $users, private TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $this->tenant->workspace();
        $this->authorize('viewAny', [User::class, $workspace]);

        return UserResource::collection($this->users->list($workspace, $request->user(), $request->only(['search', 'role', 'status', 'page', 'per_page'])));
    }

    public function store(StoreUserRequest $request): UserResource
    {
        return new UserResource($this->users->create($this->tenant->workspace(), $request->user(), $request->validated()));
    }

    public function show(string $workspace, User $user): UserResource
    {
        $this->ensureMember($user);
        $this->authorize('view', $user);

        return new UserResource($user->load(['workspace', 'clientAccess']));
    }

    public function update(UpdateUserRequest $request, string $workspace, User $user): UserResource
    {
        $this->ensureMember($user);

        return new UserResource($this->users->update($user, $request->user(), $request->validated()));
    }

    public function activate(Request $request, string $workspace, User $user): UserResource
    {
        $this->ensureMember($user);
        $this->authorize('update', $user);

        return new UserResource($this->users->setActive($user, $request->user(), true));
    }

    public function deactivate(Request $request, string $workspace, User $user): UserResource
    {
        $this->ensureMember($user);
        $this->authorize('deactivate', $user);

        return new UserResource($this->users->setActive($user, $request->user(), false));
    }

    public function removeFromWorkspace(Request $request, string $workspace, User $user): UserResource
    {
        $this->ensureMember($user);
        $this->authorize('removeFromWorkspace', $user);

        return new UserResource($this->users->removeFromWorkspace($user, $request->user()));
    }

    /**
     * {user} is bound globally, so confirm it belongs to the workspace in the URL.
     */
    private function ensureMember(User $user): void
    {
        abort_unless((int) $user->workspace_id === (int) $this->tenant->workspace()->id, 404);
    }
}
