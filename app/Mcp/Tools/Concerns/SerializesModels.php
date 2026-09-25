<?php

namespace App\Mcp\Tools\Concerns;

use App\Http\Resources\ClientDocumentResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\TaskResource;

trait SerializesModels
{
    protected function clientPayload($client): array
    {
        return (new ClientResource($client))->resolve();
    }

    protected function documentPayload($document): array
    {
        return (new ClientDocumentResource($document))->resolve();
    }

    protected function taskPayload($task): array
    {
        return (new TaskResource($task))->resolve();
    }
}
