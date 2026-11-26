<?php

namespace App\Models;

use App\Enums\UpworkRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['status', 'trigger', 'started_at', 'finished_at', 'api_calls', 'search_calls', 'detail_calls', 'jobs_found', 'jobs_new', 'search_matches', 'errors', 'claude_tokens_used'])]
class UpworkRun extends Model
{
    public function jobs(): HasMany
    {
        return $this->hasMany(UpworkJob::class);
    }

    /**
     * Records an error without any credential or token in it.
     */
    public function addError(string $type, string $message, array $context = []): void
    {
        $errors = $this->errors ?? [];
        $errors[] = ['type' => $type, 'message' => mb_substr($message, 0, 500), 'context' => $context, 'at' => now()->toISOString()];
        $this->errors = $errors;
    }

    protected function casts(): array
    {
        return [
            'status' => UpworkRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'errors' => 'array',
            'search_matches' => 'array',
        ];
    }
}
