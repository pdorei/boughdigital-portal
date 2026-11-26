<?php

namespace Tests\Concerns;

use App\Enums\IntegrationStatus;
use App\Enums\PlatformIntegrationProvider;
use App\Enums\UserRole;
use App\Models\PlatformIntegration;
use App\Models\User;
use App\Services\Upwork\Client\FakeUpworkClient;
use App\Services\Upwork\Client\UpworkClient;
use Illuminate\Support\Facades\Http;

trait UpworkFixtures
{
    protected const CLAUDE_KEY = 'sk-ant-test-SECRET-claude-key-0123456789';

    protected const UPWORK_TOKEN = 'upwork-SECRET-access-token-abcdef';

    protected const UPWORK_REFRESH = 'upwork-SECRET-refresh-token-ghijkl';

    protected function superAdmin(): User
    {
        return User::factory()->create(['workspace_id' => null, 'role' => UserRole::SuperAdmin, 'is_active' => true]);
    }

    protected function connectUpwork(): PlatformIntegration
    {
        return PlatformIntegration::query()->create([
            'provider' => PlatformIntegrationProvider::Upwork,
            'status' => IntegrationStatus::Connected,
            'credentials' => ['access_token' => self::UPWORK_TOKEN, 'refresh_token' => self::UPWORK_REFRESH, 'expires_at' => now()->addHour()->getTimestamp()],
            'settings' => ['account_name' => 'Robert Test'],
            'connected_at' => now(),
        ]);
    }

    protected function connectClaude(float $cap = 50): PlatformIntegration
    {
        return PlatformIntegration::query()->create([
            'provider' => PlatformIntegrationProvider::Anthropic,
            'status' => IntegrationStatus::Connected,
            'credentials' => ['api_key' => self::CLAUDE_KEY],
            'settings' => ['model' => 'claude-sonnet-5-5', 'fit_model' => 'claude-haiku-5-5', 'monthly_cap_usd' => $cap],
            'connected_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    protected function fakeUpwork(array $nodes, ?int $rateLimitAfter = null): FakeUpworkClient
    {
        $client = new FakeUpworkClient($nodes, $rateLimitAfter);
        $this->app->instance(UpworkClient::class, $client);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function node(string $id, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $id,
            'ciphertext' => '~01'.$id,
            'title' => 'SEO content writer for SaaS blog',
            'description' => 'Ongoing SEO content writer needed for long-form articles.',
            'posted_minutes_ago' => 10,
            'experienceLevel' => 'EXPERT',
            'amount' => ['rawValue' => '0'],
            'hourlyBudgetMin' => ['rawValue' => '30'],
            'hourlyBudgetMax' => ['rawValue' => '50'],
            'skills' => [['name' => 'seo', 'prettyName' => 'SEO']],
            'totalApplicants' => 5,
            'client' => ['totalHires' => 5, 'totalPostedJobs' => 10, 'totalReviews' => 4, 'totalFeedback' => 4.9, 'verificationStatus' => 'VERIFIED', 'totalSpent' => ['rawValue' => '20000'], 'location' => ['country' => 'United States']],
        ], $overrides);
    }

    /**
     * A Claude Messages API response whose text is $text.
     *
     * @return array<string, mixed>
     */
    protected function claudeResponse(string $text, string $model = 'claude-haiku-5-5'): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => $model,
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200],
        ];
    }

    protected function fakeClaude(string ...$texts): void
    {
        $sequence = Http::sequence();
        foreach ($texts as $text) {
            $sequence->push($this->claudeResponse($text));
        }

        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    protected function coverLetter(int $words = 180): string
    {
        return trim(str_repeat('Your content plan needs steady articles that answer real buyer questions. ', (int) ceil($words / 11)));
    }
}
