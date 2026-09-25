<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageSettingsService
{
    private const KEY = 'storage';

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return array_replace($this->defaults(), $this->stored());
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $settings = $this->settings();

        return [
            'driver' => $settings['driver'],
            's3' => [
                'access_key_id' => $this->mask($settings['s3']['access_key_id'] ?? ''),
                'secret_access_key' => $this->mask($settings['s3']['secret_access_key'] ?? ''),
                'region' => $settings['s3']['region'] ?? '',
                'bucket' => $settings['s3']['bucket'] ?? '',
                'endpoint' => $settings['s3']['endpoint'] ?? '',
                'url' => $settings['s3']['url'] ?? '',
                'use_path_style_endpoint' => (bool) ($settings['s3']['use_path_style_endpoint'] ?? false),
            ],
            'warning' => 'Changing storage affects new uploads. Existing files are not migrated automatically.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        $current = $this->settings();
        $s3 = array_replace($current['s3'], $data['s3'] ?? []);

        foreach (['access_key_id', 'secret_access_key'] as $secret) {
            if (! array_key_exists($secret, $data['s3'] ?? []) || blank($data['s3'][$secret])) {
                $s3[$secret] = $current['s3'][$secret] ?? '';
            }
        }

        $settings = [
            'driver' => $data['driver'] ?? $current['driver'],
            's3' => $s3,
        ];

        DB::table('application_settings')->updateOrInsert(
            ['key' => self::KEY],
            [
                'encrypted_value' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return $this->publicSettings();
    }

    public function activeDisk(): string
    {
        return $this->settings()['driver'] === 's3' ? 's3' : 'local';
    }

    public function configureDisk(?string $disk = null): void
    {
        if (($disk ?? $this->activeDisk()) !== 's3') {
            return;
        }

        $settings = $this->settings()['s3'];

        config([
            'filesystems.disks.s3.key' => $settings['access_key_id'] ?: config('filesystems.disks.s3.key'),
            'filesystems.disks.s3.secret' => $settings['secret_access_key'] ?: config('filesystems.disks.s3.secret'),
            'filesystems.disks.s3.region' => $settings['region'] ?: config('filesystems.disks.s3.region'),
            'filesystems.disks.s3.bucket' => $settings['bucket'] ?: config('filesystems.disks.s3.bucket'),
            'filesystems.disks.s3.endpoint' => $settings['endpoint'] ?: config('filesystems.disks.s3.endpoint'),
            'filesystems.disks.s3.url' => $settings['url'] ?: config('filesystems.disks.s3.url'),
            'filesystems.disks.s3.use_path_style_endpoint' => (bool) ($settings['use_path_style_endpoint'] ?? false),
        ]);

    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(?string $driver = null): array
    {
        $disk = in_array($driver, ['local', 's3'], true) ? $driver : $this->activeDisk();
        $this->configureDisk($disk);

        $path = 'storage-tests/'.Str::uuid().'.txt';

        try {
            Storage::disk($disk)->put($path, 'Bough Digital storage test');
            $readable = Storage::disk($disk)->exists($path);
            Storage::disk($disk)->delete($path);

            return $readable
                ? ['ok' => true, 'message' => 'Connection successful.']
                : ['ok' => false, 'message' => 'Storage write completed, but the test file could not be read.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => 'Storage connection failed: '.$exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'driver' => config('filesystems.default', 'local') === 's3' ? 's3' : 'local',
            's3' => [
                'access_key_id' => (string) config('filesystems.disks.s3.key', ''),
                'secret_access_key' => (string) config('filesystems.disks.s3.secret', ''),
                'region' => (string) config('filesystems.disks.s3.region', ''),
                'bucket' => (string) config('filesystems.disks.s3.bucket', ''),
                'endpoint' => (string) config('filesystems.disks.s3.endpoint', ''),
                'url' => (string) config('filesystems.disks.s3.url', ''),
                'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint', false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if (! Schema::hasTable('application_settings')) {
            return [];
        }

        $encrypted = DB::table('application_settings')->where('key', self::KEY)->value('encrypted_value');

        if (! $encrypted) {
            return [];
        }

        return json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
    }

    private function mask(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        return str_repeat('*', 8).substr($value, -4);
    }
}
