<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'client_id', 'task_category_id', 'assigned_to', 'created_by', 'title', 'description', 'status', 'source', 'priority', 'due_date', 'estimated_hours', 'metadata'])]
class Task extends Model
{
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Restricted users see tasks for their clients plus anything assigned to or created by them.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->role->canAccessAllWorkspaceClients()) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereIn('tasks.client_id', $user->clientAccess()->select('clients.id'))
            ->orWhere('tasks.assigned_to', $user->id)
            ->orWhere('tasks.created_by', $user->id));
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'estimated_hours' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
