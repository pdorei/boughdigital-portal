<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\PlatformIntegrationProvider;
use App\Http\Controllers\Controller;
use App\Services\Integrations\PlatformIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings > Connections. Responses carry configured flags only: API keys and
 * OAuth tokens never leave the backend.
 */
class PlatformConnectionController extends Controller
{
    public function __construct(private PlatformIntegrationService $integrations) {}

    public function index(): JsonResponse
    {
        $this->authorize('manage-platform-settings');

        return response()->json(['data' => $this->connections()]);
    }

    public function updateAnthropic(Request $request): JsonResponse
    {
        $this->authorize('manage-platform-settings');

        $models = array_keys(config('services.anthropic.models', []));
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'min:20', 'max:300', 'regex:/^\S+$/'],
            'model' => ['sometimes', Rule::in($models)],
            'fit_model' => ['sometimes', Rule::in($models)],
            'monthly_cap_usd' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
        ], ['api_key.regex' => 'The API key cannot contain spaces.']);

        $this->integrations->updateAnthropic($request->user(), $data);

        return response()->json(['data' => $this->connections(), 'message' => 'Claude connection saved.']);
    }

    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $this->authorize('manage-platform-settings');

        $provider = PlatformIntegrationProvider::tryFrom($provider) ?? abort(404);
        $this->integrations->disconnect($provider, $request->user());

        return response()->json(['data' => $this->connections(), 'message' => "{$provider->label()} disconnected."]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function connections(): array
    {
        return collect(PlatformIntegrationProvider::cases())
            ->mapWithKeys(fn (PlatformIntegrationProvider $provider) => [$provider->value => $this->integrations->publicStatus($provider)])
            ->all();
    }
}
