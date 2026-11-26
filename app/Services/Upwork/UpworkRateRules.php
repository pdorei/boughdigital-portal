<?php

namespace App\Services\Upwork;

use App\Enums\UpworkJobType;

/**
 * What to bid, decided in code (never by Claude):
 *
 * Hourly, using the top of the client's range:
 *   top rate below the minimum ($20)          -> skip
 *   no rate given, or below the premium ($30) -> standard bid ($25)
 *   premium rate or more                      -> premium bid ($30)
 *
 * Fixed price:
 *   budget at or above the minimum ($1,000)   -> bid the client's budget
 *   budget below the minimum                  -> skip
 *   no budget                                 -> review
 */
class UpworkRateRules
{
    /**
     * @param  array{min_hourly_rate: int|float, min_fixed_budget: int|float}  $fit
     * @param  array{hourly_bid_standard: int|float, hourly_bid_premium: int|float}  $bidding
     */
    public function __construct(private array $fit, private array $bidding) {}

    /**
     * @param  array<string, mixed>  $settings  the full Upwork settings
     */
    public static function fromSettings(array $settings): self
    {
        return new self($settings['fit'], $settings['bidding']);
    }

    public function decide(UpworkJobType $type, ?float $hourlyMin, ?float $hourlyMax, ?float $fixedBudget): RateDecision
    {
        return $type === UpworkJobType::Hourly
            ? $this->hourly($hourlyMax ?? $hourlyMin)
            : $this->fixed($fixedBudget);
    }

    private function hourly(?float $topRate): RateDecision
    {
        $minimum = (float) $this->fit['min_hourly_rate'];
        $standard = (float) $this->bidding['hourly_bid_standard'];
        $premium = (float) $this->bidding['hourly_bid_premium'];

        if ($topRate === null || $topRate <= 0) {
            return new RateDecision(RateDecision::BID, UpworkJobType::Hourly, $standard, 'No hourly rate given: standard bid.');
        }

        if ($topRate < $minimum) {
            return new RateDecision(RateDecision::SKIP, UpworkJobType::Hourly, null, sprintf('Top rate $%s is below the $%s minimum.', $this->money($topRate), $this->money($minimum)));
        }

        if ($topRate < $premium) {
            return new RateDecision(RateDecision::BID, UpworkJobType::Hourly, $standard, sprintf('Top rate $%s: standard bid.', $this->money($topRate)));
        }

        return new RateDecision(RateDecision::BID, UpworkJobType::Hourly, $premium, sprintf('Top rate $%s: premium bid.', $this->money($topRate)));
    }

    private function fixed(?float $budget): RateDecision
    {
        $minimum = (float) $this->fit['min_fixed_budget'];

        if ($budget === null || $budget <= 0) {
            return new RateDecision(RateDecision::REVIEW, UpworkJobType::Fixed, null, 'Fixed price with no budget: needs review.');
        }

        if ($budget < $minimum) {
            return new RateDecision(RateDecision::SKIP, UpworkJobType::Fixed, null, sprintf('Budget $%s is below the $%s minimum.', $this->money($budget), $this->money($minimum)));
        }

        return new RateDecision(RateDecision::BID, UpworkJobType::Fixed, $budget, sprintf('Bid the client budget of $%s.', $this->money($budget)));
    }

    private function money(float $value): string
    {
        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
    }
}
