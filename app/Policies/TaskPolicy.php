<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

class TaskPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->belongsToWorkspace($task->workspace_id)) {
            return false;
        }

        return $this->isAssignee($user, $task)
            || (int) $task->created_by === (int) $user->id
            || ($task->client_id ? $user->canAccessClient($task->client_id) : $user->role->canAccessAllWorkspaceClients());
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace) && $user->role->canManageTasks();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->role->canManageTasks() && $this->view($user, $task);
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->update($user, $task)
            || ($user->belongsToWorkspace($task->workspace_id) && $this->isAssignee($user, $task));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->belongsToWorkspace($task->workspace_id) && $user->role->canManageWorkspace();
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return (int) $task->assigned_to === (int) $user->id;
    }
}
