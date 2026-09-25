<?php

namespace App\Services\Tasks;

use App\Enums\ActivityEvent;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    private const EDITABLE_FIELDS = ['title', 'description', 'client_id', 'assigned_to', 'status', 'priority', 'due_date', 'estimated_hours'];

    public function __construct(private ActivityLogger $activity) {}

    public function list(Workspace $workspace, User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = TaskStatus::tryFrom((string) ($filters['status'] ?? ''));
        $priority = TaskPriority::tryFrom((string) ($filters['priority'] ?? ''));
        $assignee = ($filters['assigned_to'] ?? null) === 'me' ? $user->id : ($filters['assigned_to'] ?? null);

        $query = Task::query()
            ->with(['client:id,name,slug', 'assignee:id,name'])
            ->whereBelongsTo($workspace)
            ->visibleTo($user)
            ->when($search !== '', fn ($query) => $query->whereLike('title', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when(! $status && filter_var($filters['open'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query->where('status', '!=', TaskStatus::Completed))
            ->when($priority, fn ($query) => $query->where('priority', $priority))
            ->when($assignee, fn ($query) => $query->where('assigned_to', (int) $assignee))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', (int) $clientId))
            ->orderByRaw('due_date is null, due_date asc')
            ->orderByDesc('created_at');

        return Pagination::paginate($query, $filters);
    }

    public function findInWorkspace(Workspace $workspace, int $id): Task
    {
        return Task::query()->whereBelongsTo($workspace)->findOrFail($id);
    }

    public function create(Workspace $workspace, User $user, array $data, string $source = 'portal'): Task
    {
        $this->ensureRelationsBelongTo($workspace, $user, $data);

        $task = Task::query()->create([
            ...Arr::only($data, self::EDITABLE_FIELDS),
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'status' => $data['status'] ?? TaskStatus::Backlog,
            'priority' => $data['priority'] ?? TaskPriority::Normal,
            'source' => 'manual',
        ]);

        $this->activity->log(ActivityEvent::TaskCreated, $workspace, $user, $task, [
            'title' => $task->title,
            'assigned_to' => $task->assigned_to,
            'status' => $task->status->value,
        ], $source);

        return $task->load(['client:id,name,slug', 'assignee:id,name']);
    }

    public function update(Task $task, User $user, array $data, string $source = 'portal'): Task
    {
        $this->ensureRelationsBelongTo($task->workspace, $user, $data);

        $task->fill(Arr::only($data, self::EDITABLE_FIELDS));
        $changes = $task->getDirty();
        $original = collect($task->getRawOriginal())->only(array_keys($changes));
        $task->save();

        $this->logChanges($task, $user, $changes, $original->all(), $source);

        return $task->load(['client:id,name,slug', 'assignee:id,name']);
    }

    public function changeStatus(Task $task, User $user, TaskStatus $status, string $source = 'portal'): Task
    {
        return $this->update($task, $user, ['status' => $status], $source);
    }

    public function delete(Task $task, User $user, string $source = 'portal'): void
    {
        if (DB::table('time_entries')->where('task_id', $task->id)->exists()) {
            throw ValidationException::withMessages([
                'task' => 'This task has logged time and cannot be deleted. Mark it completed instead.',
            ]);
        }

        DB::transaction(function () use ($task, $user, $source): void {
            $this->activity->log(ActivityEvent::TaskDeleted, $task->workspace, $user, $task, ['title' => $task->title], $source);

            $task->delete();
        });
    }

    /**
     * IDs arrive from the browser or an API client, so a task may only point
     * at clients the actor can see and at active members of the same workspace.
     */
    private function ensureRelationsBelongTo(Workspace $workspace, User $user, array $data): void
    {
        if (filled($data['client_id'] ?? null)) {
            $clientVisible = Client::query()
                ->whereBelongsTo($workspace)
                ->visibleTo($user)
                ->whereKey($data['client_id'])
                ->exists();

            if (! $clientVisible) {
                throw ValidationException::withMessages(['client_id' => 'Choose a client from this workspace.']);
            }
        }

        if (filled($data['assigned_to'] ?? null)) {
            $assignable = User::query()
                ->whereBelongsTo($workspace)
                ->where('is_active', true)
                ->where('role', '!=', UserRole::SuperAdmin)
                ->whereKey($data['assigned_to'])
                ->exists();

            if (! $assignable) {
                throw ValidationException::withMessages(['assigned_to' => 'Tasks can only be assigned to active members of this workspace.']);
            }
        }
    }

    private function logChanges(Task $task, User $user, array $changes, array $original, string $source): void
    {
        $details = ['title' => $task->title];

        if (array_key_exists('assigned_to', $changes)) {
            $this->activity->log(ActivityEvent::TaskAssigned, $task->workspace, $user, $task, [
                ...$details,
                'from' => $original['assigned_to'] ?? null,
                'to' => $task->assigned_to,
            ], $source);
        }

        if (array_key_exists('status', $changes)) {
            $this->activity->log(ActivityEvent::TaskStatusChanged, $task->workspace, $user, $task, [
                ...$details,
                'from' => $original['status'] ?? null,
                'to' => $task->status->value,
            ], $source);
        }

        $otherChanges = array_diff(array_keys($changes), ['assigned_to', 'status', 'updated_at']);

        if ($otherChanges !== []) {
            $this->activity->log(ActivityEvent::TaskUpdated, $task->workspace, $user, $task, [
                ...$details,
                'fields' => array_values($otherChanges),
            ], $source);
        }
    }
}
