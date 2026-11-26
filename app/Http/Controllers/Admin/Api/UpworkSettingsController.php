<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upwork\UpworkSettingsRequest;
use App\Services\Upwork\UpworkSettingsService;
use Illuminate\Http\JsonResponse;

class UpworkSettingsController extends Controller
{
    public function __construct(private UpworkSettingsService $settings) {}

    public function show(): JsonResponse
    {
        $this->authorize('manage-platform-settings');

        return response()->json(['data' => $this->settings->settings()]);
    }

    public function update(UpworkSettingsRequest $request): JsonResponse
    {
        $this->authorize('manage-platform-settings');

        return response()->json([
            'data' => $this->settings->update($request->validated(), $request->user()),
            'message' => 'Upwork settings saved.',
        ]);
    }
}
