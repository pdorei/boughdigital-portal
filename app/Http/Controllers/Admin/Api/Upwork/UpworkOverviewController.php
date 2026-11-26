<?php

namespace App\Http\Controllers\Admin\Api\Upwork;

use App\Enums\PlatformIntegrationProvider;
use App\Http\Controllers\Controller;
use App\Jobs\Upwork\DiscoverUpworkJobs;
use App\Services\Integrations\PlatformIntegrationService;
use App\Services\Upwork\UpworkOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpworkOverviewController extends Controller
{
    public function __construct(private UpworkOverviewService $overview) {}

    public function show(): JsonResponse
    {
        $this->authorize('manage-upwork');

        return response()->json(['data' => $this->overview->summary()]);
    }

    /**
     * Queues one discovery run now (ignores the interval, not the connection).
     */
    public function run(PlatformIntegrationService $integrations): JsonResponse
    {
        $this->authorize('manage-upwork');

        if (! $integrations->get(PlatformIntegrationProvider::Upwork)->isConnected()) {
            throw ValidationException::withMessages(['upwork' => 'Connect Upwork in Connections first.']);
        }

        DiscoverUpworkJobs::dispatch('manual');

        return response()->json(['message' => 'Run queued. Results appear here when it finishes.'], 202);
    }
}
