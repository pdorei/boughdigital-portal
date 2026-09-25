<?php

namespace App\Services\Workspaces;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class WorkspaceService
{
    public const SETTING_KEYS = ['timezone', 'date_format', 'datetime_format'];

    private const AUDITED_FIELDS = ['name', 'slug', 'primary_colour', 'accent_colour', 'is_active'];

    public function __construct(private ActivityLogger $activity, private WorkspaceStorageService $storage) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $sort = in_array($filters['sort'] ?? null, ['name', 'slug', 'created_at'], true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Workspace::query()
            ->withCount(['users', 'clients'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('slug', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sort, $direction);

        return Pagination::paginate($query, $filters);
    }

    public function create(User $user, array $data, string $source = 'portal'): Workspace
    {
        return DB::transaction(function () use ($user, $data, $source): Workspace {
            $workspace = Workspace::query()->create([
                ...Arr::only($data, ['name', 'slug', 'primary_colour', 'accent_colour']),
                'settings' => $this->mergeSettings([], $data['settings'] ?? []),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->activity->log(ActivityEvent::WorkspaceCreated, $workspace, $user, $workspace, $workspace->only(self::AUDITED_FIELDS), $source);

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $this->replaceLogo($workspace, $user, $data['logo'], $source);
            }

            return $workspace;
        });
    }

    public function update(Workspace $workspace, User $user, array $data, string $source = 'portal'): Workspace
    {
        $before = $workspace->only(self::AUDITED_FIELDS);

        $workspace->fill(Arr::only($data, self::AUDITED_FIELDS));

        if (array_key_exists('settings', $data)) {
            $workspace->settings = $this->mergeSettings($workspace->settings ?? [], $data['settings'] ?? []);
        }

        $workspace->save();
        $this->logUpdate($workspace, $user, $before, $source);

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $this->replaceLogo($workspace, $user, $data['logo'], $source);
        } elseif ($data['remove_logo'] ?? false) {
            $this->removeLogo($workspace, $user, $source);
        }

        return $workspace;
    }

    public function updateSettings(Workspace $workspace, User $user, array $settings, string $source = 'portal'): Workspace
    {
        $before = Arr::only($workspace->settings ?? [], self::SETTING_KEYS);

        $workspace->settings = $this->mergeSettings($workspace->settings ?? [], $settings);
        $workspace->save();

        $this->activity->log(ActivityEvent::WorkspaceSettingsUpdated, $workspace, $user, $workspace, [
            'before' => $before,
            'after' => Arr::only($workspace->settings, self::SETTING_KEYS),
        ], $source);

        return $workspace;
    }

    public function setActive(Workspace $workspace, User $user, bool $active, string $source = 'portal'): Workspace
    {
        return $this->update($workspace, $user, ['is_active' => $active], $source);
    }

    /**
     * The new file is stored and recorded before the old one is removed, so a
     * failure at any step leaves the current logo in place.
     */
    public function replaceLogo(Workspace $workspace, User $user, UploadedFile $file, string $source = 'portal'): Workspace
    {
        $previous = ['disk' => $workspace->logo_disk, 'path' => $workspace->logo_path];
        $stored = $this->storage->storeLogo($workspace, $file);

        try {
            $workspace->forceFill(['logo_disk' => $stored['disk'], 'logo_path' => $stored['path']])->save();
        } catch (Throwable $exception) {
            $this->storage->delete($stored['disk'], $stored['path']);

            throw $exception;
        }

        $this->storage->delete($previous['disk'], $previous['path']);

        $event = $previous['path'] ? ActivityEvent::WorkspaceLogoReplaced : ActivityEvent::WorkspaceLogoUploaded;
        $this->activity->log($event, $workspace, $user, $workspace, ['name' => $workspace->name], $source);

        return $workspace;
    }

    public function removeLogo(Workspace $workspace, User $user, string $source = 'portal'): Workspace
    {
        if (blank($workspace->logo_path)) {
            return $workspace;
        }

        $previous = ['disk' => $workspace->logo_disk, 'path' => $workspace->logo_path];

        $workspace->forceFill(['logo_disk' => null, 'logo_path' => null])->save();
        $this->storage->delete($previous['disk'], $previous['path']);

        $this->activity->log(ActivityEvent::WorkspaceLogoRemoved, $workspace, $user, $workspace, ['name' => $workspace->name], $source);

        return $workspace;
    }

    /**
     * Only known keys are touched so settings written by newer features survive.
     * A blank value falls back to the application default.
     */
    private function mergeSettings(array $current, array $changes): array
    {
        foreach (Arr::only($changes, self::SETTING_KEYS) as $key => $value) {
            if (blank($value)) {
                unset($current[$key]);
            } else {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    private function logUpdate(Workspace $workspace, User $user, array $before, string $source): void
    {
        $changed = array_keys($workspace->getChanges());

        if (in_array('slug', $changed, true)) {
            $this->activity->log(ActivityEvent::WorkspaceSlugChanged, $workspace, $user, $workspace, [
                'from' => $before['slug'],
                'to' => $workspace->slug,
            ], $source);
        }

        if (in_array('is_active', $changed, true)) {
            $event = $workspace->is_active ? ActivityEvent::WorkspaceActivated : ActivityEvent::WorkspaceDeactivated;
            $this->activity->log($event, $workspace, $user, $workspace, ['name' => $workspace->name], $source);
        }

        $otherChanges = array_values(array_diff($changed, ['slug', 'is_active', 'updated_at']));

        if ($otherChanges !== []) {
            $this->activity->log(ActivityEvent::WorkspaceUpdated, $workspace, $user, $workspace, [
                'name' => $workspace->name,
                'fields' => $otherChanges,
            ], $source);
        }
    }
}
