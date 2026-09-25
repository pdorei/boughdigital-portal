<?php

namespace App\Services\Workspaces;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanent deletion is only for workspaces created by mistake: deactivated
 * and holding no clients, tasks or documents. Workspaces with business history
 * are deactivated instead. Activity rows survive (workspace_id is nulled) and
 * members keep their platform accounts.
 */
class WorkspaceDeletionService
{
    public function __construct(private ActivityLogger $activity, private WorkspaceStorageService $storage) {}

    public function delete(Workspace $workspace, User $actor, string $confirmation, string $source = 'portal'): void
    {
        $this->ensureDeletable($workspace, $confirmation);

        DB::transaction(function () use ($workspace, $actor, $source): void {
            $members = User::query()->whereBelongsTo($workspace)->get();

            foreach ($members as $member) {
                $member->tokens()->delete();
            }

            User::query()->whereBelongsTo($workspace)->update(['workspace_id' => null]);

            $this->activity->log(ActivityEvent::WorkspaceDeleted, null, $actor, $workspace, [
                'workspace_id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'detached_members' => $members->count(),
            ], $source);

            $workspace->delete();
        });

        $this->storage->deleteWorkspaceFiles($workspace);
    }

    private function ensureDeletable(Workspace $workspace, string $confirmation): void
    {
        if ($confirmation !== $workspace->slug) {
            throw ValidationException::withMessages(['confirmation' => 'Type the workspace slug to confirm deletion.']);
        }

        if ($workspace->is_active) {
            throw ValidationException::withMessages(['workspace' => 'Deactivate the workspace before deleting it.']);
        }

        if ($workspace->clients()->exists() || $workspace->tasks()->exists() || $workspace->documents()->exists()) {
            throw ValidationException::withMessages([
                'workspace' => 'This workspace has clients, tasks or documents. Keep it deactivated so its history is preserved.',
            ]);
        }
    }
}
