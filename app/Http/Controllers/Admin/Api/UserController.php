<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [User::class, null]);

        return UserResource::collection($this->users->list(null, $request->user(), $request->only(['search', 'workspace_id', 'role', 'status', 'page', 'per_page'])));
    }

    public function store(StoreUserRequest $request): UserResource
    {
        return new UserResource($this->users->create($request->targetWorkspace(), $request->user(), $request->validated()));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['workspace', 'clientAccess']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->user(), $request->validated()));
    }

    public function activate(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        return new UserResource($this->users->setActive($user, $request->user(), true));
    }

    public function deactivate(Request $request, User $user): UserResource
    {
        $this->authorize('deactivate', $user);

        return new UserResource($this->users->setActive($user, $request->user(), false));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user, $request->user());

        return response()->json(['message' => 'User deleted.']);
    }
}
