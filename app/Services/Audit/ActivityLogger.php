<?php

namespace App\Services\Audit;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    private const REDACTED_KEYS = [
        'password',
        'remember_token',
        'token',
        'secret',
        'credentials',
        'encrypted_credentials',
        'smtp_password',
        'access_key_id',
        'secret_access_key',
    ];

    public function log(
        ActivityEvent $event,
        ?Workspace $workspace,
        ?User $actor,
        ?Model $subject = null,
        array $details = [],
        string $source = 'portal',
    ): ActivityLog {
        $request = request();

        return ActivityLog::query()->create([
            'workspace_id' => $workspace?->id,
            'user_id' => $actor?->id,
            'client_id' => $this->clientIdFor($subject, $details),
            'action' => $event->value,
            'entity_type' => $subject ? $subject::class : null,
            'entity_id' => $subject?->getKey(),
            'details' => $this->redact($details),
            'source' => $source,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'request_id' => $request instanceof Request ? $request->headers->get('X-Request-Id') : null,
        ]);
    }

    private function clientIdFor(?Model $subject, array $details): ?int
    {
        if ($subject instanceof Client) {
            return $subject->id;
        }

        return $subject?->getAttribute('client_id') ?? ($details['client_id'] ?? null);
    }

    private function redact(array $details): array
    {
        foreach ($details as $key => $value) {
            if (in_array($key, self::REDACTED_KEYS, true)) {
                unset($details[$key]);
            } elseif (is_array($value)) {
                $details[$key] = $this->redact($value);
            }
        }

        return $details;
    }
}
