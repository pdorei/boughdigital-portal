<?php

namespace App\Services\Users;

use App\Enums\ActivityEvent;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const AUDITED_FIELDS = ['workspace_id', 'name', 'email', 'role', 'avatar_url', 'is_active'];

    public function __construct(private ActivityLogger $activity) {}

    public function list(?Workspace $workspace, User $actor, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = UserRole::tryFrom((string) ($filters['role'] ?? ''));
        $status = $filters['status'] ?? null;

        $query = User::query()
            ->with(['workspace', 'clientAccess.workspace'])
            ->withCount('clientAccess')
            ->when($workspace, fn ($query) => $query->whereBelongsTo($workspace)->where('role', '!=', UserRole::SuperAdmin))
            ->when(! $workspace && ! empty($filters['workspace_id']), fn ($query) => $query->where('workspace_id', (int) $filters['workspace_id']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name');

        return Pagination::paginate($query, $filters);
    }

    public function create(?Workspace $workspace, User $actor, array $data, string $source = 'portal'): User
    {
        $user = User::query()->create([
            'workspace_id' => $data['workspace_id'] ?? $workspace?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? Str::password(16)),
            'role' => $data['role'],
            'avatar_url' => $data['avatar_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncClientAccess($user, $data['client_ids'] ?? []);

        $this->activity->log(ActivityEvent::UserCreated, $user->workspace, $actor, $user, $this->auditSnapshot($user), $source);

        if ($user->workspace) {
            $this->activity->log(ActivityEvent::WorkspaceMemberAdded, $user->workspace, $actor, $user, [
                'name' => $user->name,
                'role' => $user->role->value,
            ], $source);
        }

        return $user->load(['workspace', 'clientAccess']);
    }

    public function update(User $user, User $actor, array $data, string $source = 'portal'): User
    {
        $before = $this->auditSnapshot($user);
        $previousWorkspace = $user->workspace;

        $user->fill(Arr::only($data, self::AUDITED_FIELDS));

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $this->guardSelfChanges($user, $actor);
        $this->guardLastAdmin($user);

        $passwordChanged = $user->isDirty('password');

        DB::transaction(function () use ($user, $data): void {
            $user->save();
            $user->unsetRelation('workspace');

            if (array_key_exists('client_ids', $data) || $user->wasChanged('workspace_id')) {
                $this->syncClientAccess($user, $data['client_ids'] ?? []);
            }
        });

        // Sessions are invalidated by AuthenticateSession / EnsureUserIsActive;
        // API tokens must be revoked explicitly.
        if ($passwordChanged || ! $user->is_active) {
            $user->tokens()->delete();
        }

        $this->logUpdate($user, $actor, $before, $previousWorkspace, $source);

        return $user->load(['workspace', 'clientAccess']);
    }

    public function setActive(User $user, User $actor, bool $active, string $source = 'portal'): User
    {
        return $this->update($user, $actor, ['is_active' => $active], $source);
    }

    /**
     * Detaches the member from the workspace but keeps the platform account and
     * every task, document and activity record that references it.
     */
    public function removeFromWorkspace(User $user, User $actor, string $source = 'portal'): User
    {
        $workspace = $user->workspace;

        if (! $workspace) {
            throw ValidationException::withMessages(['user' => 'This user is not a member of a workspace.']);
        }

        $user->workspace_id = null;
        $this->guardSelfChanges($user, $actor);
        $this->guardLastAdmin($user);

        DB::transaction(function () use ($user): void {
            $user->save();
            $user->unsetRelation('workspace');
            $user->clientAccess()->detach();
            $user->tokens()->delete();
        });

        $this->activity->log(ActivityEvent::WorkspaceMemberRemoved, $workspace, $actor, $user, [
            'name' => $user->name,
            'role' => $user->role->value,
        ], $source);

        return $user->load(['workspace', 'clientAccess']);
    }

    /**
     * Permanently deletes the platform account. Ownership columns on tasks and
     * documents become null through their foreign keys, so history remains.
     */
    public function delete(User $user, User $actor, string $source = 'portal'): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => 'Super Admin accounts cannot be deleted.']);
        }

        if (DB::table('time_entries')->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This user has logged time and cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $workspace = $user->workspace;
        $user->workspace_id = null;
        $this->guardLastAdmin($user);

        DB::transaction(function () use ($user, $workspace, $actor, $source): void {
            $this->activity->log(ActivityEvent::UserDeleted, $workspace, $actor, $user, [
                'name' => $user->name,
                'role' => $user->role->value,
            ], $source);

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });
    }

    private function guardSelfChanges(User $user, User $actor): void
    {
        if (! $user->is($actor)) {
            return;
        }

        if ($user->isDirty('role') || $user->isDirty('workspace_id') || ($user->isDirty('is_active') && ! $user->is_active)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot change your own role, workspace or active status.',
            ]);
        }
    }

    /**
     * Blocks any change that would leave a workspace without an active admin.
     */
    private function guardLastAdmin(User $user): void
    {
        $wasActiveAdmin = $user->getOriginal('role') === UserRole::Admin
            && $user->getOriginal('is_active')
            && $user->getOriginal('workspace_id') !== null;

        $staysActiveAdmin = $user->role === UserRole::Admin
            && $user->is_active
            && (int) $user->workspace_id === (int) $user->getOriginal('workspace_id');

        if (! $wasActiveAdmin || $staysActiveAdmin) {
            return;
        }

        $otherAdmins = User::query()
            ->where('workspace_id', $user->getOriginal('workspace_id'))
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $otherAdmins) {
            throw ValidationException::withMessages([
                'user' => 'A workspace must keep at least one active admin. Promote another member first.',
            ]);
        }
    }

    private function logUpdate(User $user, User $actor, array $before, ?Workspace $previousWorkspace, string $source): void
    {
        $after = $this->auditSnapshot($user);
        $changed = array_keys(array_diff_assoc(
            array_map(fn ($value) => json_encode($value), $after),
            array_map(fn ($value) => json_encode($value), $before),
        ));

        if (in_array('workspace_id', $changed, true)) {
            if ($previousWorkspace) {
                $this->activity->log(ActivityEvent::WorkspaceMemberRemoved, $previousWorkspace, $actor, $user, ['name' => $user->name], $source);
            }

            if ($user->workspace) {
                $this->activity->log(ActivityEvent::WorkspaceMemberAdded, $user->workspace, $actor, $user, ['name' => $user->name, 'role' => $after['role']], $source);
            }
        }

        if (in_array('role', $changed, true)) {
            $this->activity->log(ActivityEvent::WorkspaceMemberRoleChanged, $user->workspace, $actor, $user, [
                'name' => $user->name,
                'from' => $before['role'],
                'to' => $after['role'],
            ], $source);
        }

        if (in_array('is_active', $changed, true)) {
            $event = $user->is_active ? ActivityEvent::UserActivated : ActivityEvent::UserDeactivated;
            $this->activity->log($event, $user->workspace, $actor, $user, ['name' => $user->name], $source);
        }

        $profileChanges = array_values(array_diff($changed, ['workspace_id', 'role', 'is_active']));

        if ($profileChanges !== [] || $user->wasChanged('password')) {
            $this->activity->log(ActivityEvent::UserUpdated, $user->workspace, $actor, $user, [
                'name' => $user->name,
                'fields' => $user->wasChanged('password') ? [...$profileChanges, 'password'] : $profileChanges,
            ], $source);
        }
    }

    private function syncClientAccess(User $user, array $clientIds): void
    {
        if (! $user->workspace_id) {
            $user->clientAccess()->sync([]);

            return;
        }

        $sync = Client::query()
            ->where('workspace_id', $user->workspace_id)
            ->whereIn('id', array_map('intval', $clientIds))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['workspace_id' => $user->workspace_id]])
            ->all();

        $user->clientAccess()->sync($sync);
    }

    private function auditSnapshot(User $user): array
    {
        $snapshot = $user->only(self::AUDITED_FIELDS);
        $snapshot['role'] = $user->role?->value;

        return $snapshot;
    }
}
