<?php

namespace Tests\Unit;

use App\Enums\UpworkJobType;
use App\Services\Upwork\RateDecision;
use App\Services\Upwork\UpworkRateRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpworkRateRulesTest extends TestCase
{
    /**
     * @return array<string, array{0: UpworkJobType, 1: ?float, 2: ?float, 3: ?float, 4: string, 5: ?float}>
     */
    public static function cases(): array
    {
        return [
            'hourly $11-22 bids $25' => [UpworkJobType::Hourly, 11, 22, null, RateDecision::BID, 25],
            'hourly top under $20 skips' => [UpworkJobType::Hourly, 10, 19, null, RateDecision::SKIP, null],
            'hourly top $19.99 skips' => [UpworkJobType::Hourly, 15, 19.99, null, RateDecision::SKIP, null],
            'hourly only min $15 skips' => [UpworkJobType::Hourly, 15, null, null, RateDecision::SKIP, null],
            'hourly top exactly $20 bids $25' => [UpworkJobType::Hourly, 15, 20, null, RateDecision::BID, 25],
            'hourly $20-29 bids $25' => [UpworkJobType::Hourly, 20, 29, null, RateDecision::BID, 25],
            'hourly top $29.99 bids $25' => [UpworkJobType::Hourly, 25, 29.99, null, RateDecision::BID, 25],
            'hourly no rate bids $25' => [UpworkJobType::Hourly, null, null, null, RateDecision::BID, 25],
            'hourly zero rate bids $25' => [UpworkJobType::Hourly, 0, 0, null, RateDecision::BID, 25],
            'hourly top exactly $30 bids $30' => [UpworkJobType::Hourly, 20, 30, null, RateDecision::BID, 30],
            'hourly $30-60 bids $30' => [UpworkJobType::Hourly, 30, 60, null, RateDecision::BID, 30],
            'hourly only min $45 bids $30' => [UpworkJobType::Hourly, 45, null, null, RateDecision::BID, 30],
            'hourly $100 top bids $30' => [UpworkJobType::Hourly, 50, 100, null, RateDecision::BID, 30],
            'fixed $1,000 bids budget' => [UpworkJobType::Fixed, null, null, 1000, RateDecision::BID, 1000],
            'fixed $2,500 bids budget' => [UpworkJobType::Fixed, null, null, 2500, RateDecision::BID, 2500],
            'fixed $5,000 bids budget' => [UpworkJobType::Fixed, null, null, 5000, RateDecision::BID, 5000],
            'fixed $999 skips' => [UpworkJobType::Fixed, null, null, 999, RateDecision::SKIP, null],
            'fixed $300 skips' => [UpworkJobType::Fixed, null, null, 300, RateDecision::SKIP, null],
            'fixed no budget goes to review' => [UpworkJobType::Fixed, null, null, null, RateDecision::REVIEW, null],
            'fixed zero budget goes to review' => [UpworkJobType::Fixed, null, null, 0, RateDecision::REVIEW, null],
        ];
    }

    #[DataProvider('cases')]
    public function test_rate_rules(UpworkJobType $type, ?float $min, ?float $max, ?float $budget, string $action, ?float $amount): void
    {
        $rules = new UpworkRateRules(
            ['min_hourly_rate' => 20, 'min_fixed_budget' => 1000],
            ['hourly_bid_standard' => 25, 'hourly_bid_premium' => 30],
        );

        $decision = $rules->decide($type, $min, $max, $budget);

        $this->assertSame($action, $decision->action, $decision->reason);
        $this->assertSame($amount, $decision->amount === null ? null : (float) $decision->amount);
        $this->assertSame($type, $decision->bidType);
    }
}
