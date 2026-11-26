<?php

namespace App\Services\Upwork;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upwork automation settings, stored in application_settings under "upwork"
 * (encrypted, like the storage settings). Saving merges into what is stored,
 * so keys the form does not know about are kept.
 */
class UpworkSettingsService
{
    private const KEY = 'upwork';

    public function __construct(private ActivityLogger $activity) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->merge($this->defaults(), $this->stored());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed>
     */
    public function update(array $data, User $user): array
    {
        $before = $this->settings();
        $stored = $this->merge($this->stored(), $data);

        $this->write($stored);

        $after = $this->settings();
        $changed = collect(array_keys($data))
            ->filter(fn (string $section) => ($before[$section] ?? null) != ($after[$section] ?? null))
            ->values()
            ->all();

        if ($changed !== []) {
            // Section names only: prompt text is not copied into the activity log.
            $this->activity->log(ActivityEvent::UpworkSettingsUpdated, null, $user, null, ['sections' => $changed]);
        }

        return $after;
    }

    /**
     * Updates the manually entered Connects balance (e.g. after a proposal is sent).
     */
    public function adjustConnectsBalance(int $spent): void
    {
        $balance = $this->get('bidding.connects_balance');

        if ($balance === null || $spent <= 0) {
            return;
        }

        $stored = $this->stored();
        data_set($stored, 'bidding.connects_balance', max(0, (int) $balance - $spent));
        $this->write($stored);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $levels = ['intermediate', 'expert'];

        return [
            'automation_enabled' => false,
            'run_interval_minutes' => 60,
            'tracks' => [
                'content' => ['keywords' => ['content writer', 'SEO content writing', 'blog writer', 'copywriting']],
                'wordpress' => ['keywords' => ['wordpress developer', 'wordpress website', 'elementor', 'woocommerce']],
            ],
            'searches' => [
                'a' => ['enabled' => true, 'job_type' => 'fixed', 'fixed_budget_min' => 1000, 'experience_levels' => $levels],
                'b' => ['enabled' => true, 'job_type' => 'hourly', 'hourly_rate_min' => 25, 'experience_levels' => $levels],
                'c' => ['enabled' => true, 'client_countries' => ['Australia'], 'experience_levels' => $levels],
            ],
            'fit' => [
                'min_hourly_rate' => 20,
                'min_fixed_budget' => 1000,
                'max_proposals' => 50,
                'max_job_age_minutes' => 90,
                'auto_accept_score' => 75,
                'review_score' => 40,
            ],
            'bidding' => [
                'hourly_bid_standard' => 25,
                'hourly_bid_premium' => 30,
                'daily_connects_cap' => 100,
                'connects_balance' => null,
                'connects_balance_floor' => 50,
                'send_delay_min_minutes' => 30,
                'send_delay_max_minutes' => 90,
                'proposal_expiry_minutes' => 180,
            ],
            'prompts' => [
                'fit_check' => $this->defaultFitPrompt(),
                'cover_letter' => $this->defaultCoverLetterPrompt(),
                'screening_answers' => $this->defaultScreeningPrompt(),
            ],
        ];
    }

    /**
     * Associative arrays merge key by key; lists (keywords, countries, levels)
     * are replaced as a whole so removing an item works.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && ! array_is_list($value) && is_array($base[$key] ?? null) && ! array_is_list($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
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

        return $encrypted ? json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function write(array $settings): void
    {
        $exists = DB::table('application_settings')->where('key', self::KEY)->exists();
        $value = ['encrypted_value' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)), 'updated_at' => now()];

        $exists
            ? DB::table('application_settings')->where('key', self::KEY)->update($value)
            : DB::table('application_settings')->insert([...$value, 'key' => self::KEY, 'created_at' => now()]);
    }

    private function defaultFitPrompt(): string
    {
        return <<<'TXT'
You screen Upwork jobs for Bough Digital, an Australian agency (Expert Vetted, 99% Job Success). We take two kinds of work:
- Content: SEO articles, blog content, website copy, content strategy.
- WordPress: builds, redesigns, Elementor/WooCommerce work, speed and maintenance.

Score how well this job fits us from 0 to 100. Good fits have a clear scope, a realistic budget, a client with hiring history, and work we can deliver well. Penalise vague scope, unpaid tests, requests to work off-platform, guaranteed-ranking demands, very low budgets, and anything outside the two tracks.

Track: {{track}}
Job:
{{job}}

Answer with the JSON object only.
TXT;
    }

    private function defaultCoverLetterPrompt(): string
    {
        return <<<'TXT'
Write an Upwork cover letter for Bough Digital (Australian agency, Expert Vetted, 99% Job Success) for the job below.

Rules:
- 150 to 220 words.
- Open with the client's problem, not with "I" or a greeting cliché.
- Show we understood the specific deliverables; mention one relevant approach or example in general terms.
- End with one short question or a clear next step.
- No email addresses, phone numbers, links or other contact details. No requests to move off Upwork.
- Do not use em dashes.
- Never promise rankings, traffic numbers or guaranteed results.
- Plain text, short paragraphs, no headings.

Track: {{track}}
Our bid: {{bid}}
Fit notes: {{fit_reason}}
Job:
{{job}}
TXT;
    }

    private function defaultScreeningPrompt(): string
    {
        return <<<'TXT'
Answer the client's screening questions for Bough Digital (Australian agency, Expert Vetted, 99% Job Success). Keep each answer to 2 to 4 sentences, specific to the job, honest, with no contact details, no em dashes and no promises of rankings or guaranteed results. If a question asks for something we cannot know (such as a portfolio link), say it can be shared in the Upwork chat.

Job:
{{job}}

Questions:
{{questions}}
TXT;
    }
}
