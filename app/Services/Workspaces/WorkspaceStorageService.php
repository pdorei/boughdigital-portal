<?php

namespace App\Services\Workspaces;

use App\Models\Client;
use App\Models\ClientCompetitor;
use App\Models\ClientDocument;
use App\Models\Workspace;
use App\Services\Storage\StorageSettingsService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Physical layout: workspaces/{workspace-id}/... keyed by immutable IDs so a
 * slug rename never moves files. Logos live on a publicly readable disk;
 * client documents live on a private disk and are only served through an
 * authorized download endpoint.
 */
class WorkspaceStorageService
{
    public function __construct(private StorageSettingsService $storageSettings) {}

    public function root(Workspace|int $workspace): string
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return "workspaces/{$id}";
    }

    public function logoDirectory(Workspace $workspace): string
    {
        return $this->root($workspace).'/logo';
    }

    public function clientDocumentsDirectory(Client $client): string
    {
        return $this->root($client->workspace_id)."/clients/{$client->id}/documents";
    }

    public function clientCompetitorsDirectory(Client $client): string
    {
        return $this->root($client->workspace_id)."/clients/{$client->id}/competitors";
    }

    public function publicDisk(): string
    {
        return $this->storageSettings->activeDisk() === 's3' ? 's3' : 'public';
    }

    public function privateDisk(): string
    {
        return $this->storageSettings->activeDisk();
    }

    /**
     * @return array{disk: string, path: string}
     */
    public function storeLogo(Workspace $workspace, UploadedFile $file): array
    {
        // A unique name per upload means a replacement never overwrites the
        // logo that is still referenced by the database.
        $name = 'logo-'.Str::lower(Str::random(12)).'.'.$this->extensionFor($file);

        return $this->store($this->publicDisk(), $this->logoDirectory($workspace), $file, $name);
    }

    /**
     * @return array{disk: string, path: string}
     */
    public function storeClientDocument(Client $client, UploadedFile $file): array
    {
        $name = Str::uuid().'.'.$this->extensionFor($file);

        return $this->store($this->privateDisk(), $this->clientDocumentsDirectory($client), $file, $name);
    }

    /**
     * Competitor logos are client business data, so they stay on the private
     * disk and are served through an authorized endpoint.
     *
     * @return array{disk: string, path: string}
     */
    public function storeCompetitorLogo(ClientCompetitor $competitor, UploadedFile $file): array
    {
        $name = "logo-{$competitor->id}-".Str::lower(Str::random(12)).'.'.$this->extensionFor($file);

        return $this->store($this->privateDisk(), $this->clientCompetitorsDirectory($competitor->client), $file, $name);
    }

    public function logoUrl(Workspace $workspace): ?string
    {
        if (blank($workspace->logo_path)) {
            return null;
        }

        return $this->disk($workspace->logo_disk ?: 'public')->url($workspace->logo_path);
    }

    public function download(ClientDocument $document): Response
    {
        $disk = $this->disk($document->storage_disk);

        abort_unless($disk->exists($document->storage_path), 404, 'The file for this document is missing.');

        $filename = $document->original_name ?: basename($document->storage_path);

        if ($document->storage_disk === 's3') {
            return redirect()->away($disk->temporaryUrl($document->storage_path, now()->addMinutes(5), [
                'ResponseContentDisposition' => 'attachment; filename="'.addcslashes($filename, '"\\').'"',
            ]));
        }

        return $disk->download($document->storage_path, $filename);
    }

    /**
     * Displays a private image in the browser (for example an <img> source).
     */
    public function inline(string $disk, string $path): Response
    {
        $filesystem = $this->disk($disk);

        abort_unless($filesystem->exists($path), 404);

        if ($disk === 's3') {
            return redirect()->away($filesystem->temporaryUrl($path, now()->addMinutes(5)));
        }

        return $filesystem->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(?string $disk, ?string $path): void
    {
        if (blank($disk) || blank($path)) {
            return;
        }

        if (! $this->disk($disk)->delete($path)) {
            Log::warning('Could not delete stored file.', ['disk' => $disk, 'path' => $path]);
        }
    }

    public function deleteWorkspaceFiles(Workspace $workspace): void
    {
        foreach (array_unique([$this->publicDisk(), $this->privateDisk(), 'public', 'local']) as $disk) {
            if (! $this->disk($disk)->deleteDirectory($this->root($workspace))) {
                Log::warning('Could not delete workspace storage directory.', ['disk' => $disk, 'workspace_id' => $workspace->id]);
            }
        }
    }

    public function disk(string $name): Filesystem
    {
        $this->storageSettings->configureDisk($name);

        return Storage::disk($name);
    }

    /**
     * @return array{disk: string, path: string}
     */
    private function store(string $disk, string $directory, UploadedFile $file, string $name): array
    {
        $path = $this->disk($disk)->putFileAs($directory, $file, $name);

        if ($path === false || ! $this->disk($disk)->exists($path)) {
            throw new RuntimeException('The file could not be stored.');
        }

        return ['disk' => $disk, 'path' => $path];
    }

    /**
     * Derived from the detected MIME type, never from the client-supplied name.
     */
    private function extensionFor(UploadedFile $file): string
    {
        return strtolower($file->guessExtension() ?: 'bin');
    }
}
