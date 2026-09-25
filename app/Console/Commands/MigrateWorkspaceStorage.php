<?php

namespace App\Console\Commands;

use App\Models\ClientDocument;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceStorageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves files stored under the old slug-based layout (workspaces/{slug}/...)
 * to the ID-based layout. Each file is copied and verified, the database is
 * updated, and only then is the old copy removed. Safe to re-run.
 */
#[Signature('workspaces:migrate-storage {--dry-run : Report what would move without changing anything}')]
#[Description('Move workspace logos and documents to the workspaces/{id}/ storage layout')]
class MigrateWorkspaceStorage extends Command
{
    private int $moved = 0;

    private int $failed = 0;

    public function handle(WorkspaceStorageService $storage): int
    {
        $this->moved = 0;
        $this->failed = 0;

        Workspace::query()->whereNotNull('logo_path')->each(function (Workspace $workspace) use ($storage): void {
            $target = $storage->logoDirectory($workspace).'/'.basename($workspace->logo_path);

            $this->relocate($storage, $workspace, 'logo_disk', 'logo_path', $target, $workspace->logo_disk ?: 'public');
        });

        ClientDocument::query()->with('client')->whereNotNull('storage_path')->each(function (ClientDocument $document) use ($storage): void {
            $target = $storage->clientDocumentsDirectory($document->client).'/'.basename($document->storage_path);

            $this->relocate($storage, $document, 'storage_disk', 'storage_path', $target, $document->storage_disk);
        });

        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved')." {$this->moved} file(s); {$this->failed} problem(s).");

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function relocate(WorkspaceStorageService $storage, Model $record, string $diskColumn, string $pathColumn, string $target, string $diskName): void
    {
        $source = $record->getAttribute($pathColumn);

        if ($source === $target) {
            return;
        }

        $label = class_basename($record)." #{$record->getKey()}";
        $disk = $storage->disk($diskName);

        if (! $disk->exists($source)) {
            $this->failed++;
            $this->error("{$label}: {$diskName}:{$source} is missing; record left unchanged.");

            return;
        }

        $this->line("{$label}: {$diskName}:{$source} -> {$target}");
        $this->moved++;

        if ($this->option('dry-run')) {
            return;
        }

        if (! $disk->copy($source, $target) || $disk->size($target) !== $disk->size($source)) {
            $this->failed++;
            $this->moved--;
            $this->error("{$label}: copy could not be verified; original kept.");

            return;
        }

        $record->forceFill([$diskColumn => $diskName, $pathColumn => $target])->save();
        $disk->delete($source);
    }
}
