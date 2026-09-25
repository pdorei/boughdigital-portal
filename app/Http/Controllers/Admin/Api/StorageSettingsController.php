<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Storage\StorageSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorageSettingsController extends Controller
{
    public function __construct(private StorageSettingsService $storage) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        return response()->json(['data' => $this->storage->publicSettings()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $data = $request->validate($this->rules());
        $this->validateS3Activation($data);

        return response()->json(['data' => $this->storage->update($data)]);
    }

    public function test(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $data = $request->validate([
            'driver' => ['nullable', Rule::in(['local', 's3'])],
        ]);

        $result = $this->storage->testConnection($data['driver'] ?? null);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'driver' => ['required', Rule::in(['local', 's3'])],
            's3' => ['array'],
            's3.access_key_id' => ['nullable', 'string', 'max:255'],
            's3.secret_access_key' => ['nullable', 'string', 'max:255'],
            's3.region' => ['nullable', 'string', 'max:100'],
            's3.bucket' => ['nullable', 'string', 'max:255'],
            's3.endpoint' => ['nullable', 'url', 'max:2048'],
            's3.url' => ['nullable', 'url', 'max:2048'],
            's3.use_path_style_endpoint' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateS3Activation(array $data): void
    {
        if (($data['driver'] ?? null) !== 's3') {
            return;
        }

        $current = $this->storage->settings();
        $s3 = $data['s3'] ?? [];

        $errors = [];
        foreach (['region', 'bucket'] as $field) {
            if (blank($s3[$field] ?? $current['s3'][$field] ?? null)) {
                $errors["s3.{$field}"] = ['This field is required when S3 is selected.'];
            }
        }

        foreach (['access_key_id', 'secret_access_key'] as $field) {
            if (blank($s3[$field] ?? null) && blank($current['s3'][$field] ?? null)) {
                $errors["s3.{$field}"] = ['This field is required when S3 is selected.'];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
