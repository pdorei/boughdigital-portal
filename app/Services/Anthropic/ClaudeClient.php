<?php

namespace App\Services\Anthropic;

use App\Enums\PlatformIntegrationProvider;
use App\Services\Integrations\PlatformIntegrationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the Claude Messages API with the key stored (encrypted) in the
 * Anthropic platform integration. Every call records token usage and an
 * estimated USD cost; calls stop once the month's spend reaches the cap.
 * Logs carry model, purpose and token counts only, never prompts or keys.
 */
class ClaudeClient
{
    public function __construct(private PlatformIntegrationService $integrations) {}

    public function proposalModel(): string
    {
        return (string) $this->integrations->get(PlatformIntegrationProvider::Anthropic)
            ->setting('model', config('services.anthropic.default_model'));
    }

    public function fitModel(): string
    {
        return (string) $this->integrations->get(PlatformIntegrationProvider::Anthropic)
            ->setting('fit_model', config('services.anthropic.default_fit_model'));
    }

    public function isConfigured(): bool
    {
        return $this->integrations->get(PlatformIntegrationProvider::Anthropic)->isConnected();
    }

    public function budgetRemaining(): float
    {
        $cap = (float) $this->integrations->get(PlatformIntegrationProvider::Anthropic)
            ->setting('monthly_cap_usd', config('services.anthropic.default_monthly_cap_usd'));

        return round($cap - $this->integrations->anthropicSpendThisMonth(), 4);
    }

    /**
     * Sends one request. With $schema the answer is constrained to that JSON
     * schema (structured outputs); callers still validate the decoded JSON.
     *
     * @param  array<string, mixed>|null  $schema
     *
     * @throws ClaudeException
     */
    public function message(string $model, string $system, string $prompt, ?array $schema = null, int $maxTokens = 8000, string $purpose = 'general'): ClaudeResponse
    {
        $integration = $this->integrations->get(PlatformIntegrationProvider::Anthropic);
        $apiKey = $integration->isConnected() ? ($integration->credentials['api_key'] ?? null) : null;

        if (blank($apiKey)) {
            throw new ClaudeException(ClaudeException::NOT_CONFIGURED, 'Claude is not connected. Add the API key in Settings > Connections.');
        }

        if ($this->budgetRemaining() <= 0) {
            throw new ClaudeException(ClaudeException::BUDGET_EXCEEDED, 'The monthly Claude spending cap has been reached.');
        }

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        if ($schema !== null) {
            $body['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        }

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => config('services.anthropic.version'),
        ];

        if (in_array($model, config('services.anthropic.refusal_fallback_models', []), true)) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks'] = 'default';
        }

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->timeout((int) config('services.anthropic.timeout', 120))
                ->post(rtrim(config('services.anthropic.base_url'), '/').'/messages', $body);
        } catch (ConnectionException) {
            throw new ClaudeException(ClaudeException::REQUEST_FAILED, 'Could not reach the Claude API.');
        }

        if ($response->failed()) {
            $type = (string) $response->json('error.type', 'error');
            Log::warning('claude.request_failed', ['purpose' => $purpose, 'model' => $model, 'status' => $response->status(), 'type' => $type]);

            if ($response->status() === 401) {
                $this->integrations->markError(PlatformIntegrationProvider::Anthropic, 'The Claude API key was rejected.');
            }

            throw new ClaudeException(ClaudeException::REQUEST_FAILED, "Claude API error ({$response->status()} {$type}).");
        }

        $payload = $response->json();
        $servedBy = (string) ($payload['model'] ?? $model);
        $inputTokens = (int) data_get($payload, 'usage.input_tokens', 0)
            + (int) data_get($payload, 'usage.cache_creation_input_tokens', 0)
            + (int) data_get($payload, 'usage.cache_read_input_tokens', 0);
        $outputTokens = (int) data_get($payload, 'usage.output_tokens', 0);
        $cost = $this->estimateCost($servedBy, $inputTokens, $outputTokens);

        $this->integrations->recordAnthropicUsage($inputTokens, $outputTokens, $cost);

        Log::info('claude.request', [
            'purpose' => $purpose,
            'model' => $servedBy,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $cost,
            'stop_reason' => $payload['stop_reason'] ?? null,
        ]);

        $stopReason = $payload['stop_reason'] ?? null;

        if ($stopReason === 'refusal') {
            throw new ClaudeException(ClaudeException::REFUSED, 'Claude declined this request.');
        }

        if ($stopReason === 'max_tokens') {
            throw new ClaudeException(ClaudeException::TRUNCATED, 'Claude ran out of output tokens before finishing.');
        }

        $text = collect($payload['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new ClaudeResponse($text, $servedBy, $inputTokens, $outputTokens, $cost);
    }

    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $models = config('services.anthropic.models', []);
        // Unknown models are priced as the most expensive configured one, so the cap stays conservative.
        $price = $models[$model] ?? collect($models)->sortByDesc('output')->first() ?? ['input' => 0, 'output' => 0];

        return round(($inputTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000, 6);
    }
}
