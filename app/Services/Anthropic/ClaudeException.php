<?php

namespace App\Services\Anthropic;

use RuntimeException;

/**
 * A Claude call that could not be made or did not produce a usable answer.
 * Messages never contain the API key or the prompt.
 */
class ClaudeException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const BUDGET_EXCEEDED = 'budget_exceeded';

    public const REQUEST_FAILED = 'request_failed';

    public const REFUSED = 'refused';

    public const TRUNCATED = 'truncated';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
