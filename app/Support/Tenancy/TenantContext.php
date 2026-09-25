<?php

namespace App\Support\Tenancy;

use App\Models\Workspace;

class TenantContext
{
    public function __construct(
        private ?Workspace $workspace = null,
    ) {}

    public function setWorkspace(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function workspace(): Workspace
    {
        abort_if($this->workspace === null, 500, 'Workspace has not been resolved.');

        return $this->workspace;
    }

    public function maybeWorkspace(): ?Workspace
    {
        return $this->workspace;
    }
}
