<?php

use App\Mcp\Servers\WorkspaceServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/{workspace}', WorkspaceServer::class)
    ->middleware(['auth:sanctum', 'workspace.resolve', 'workspace.access']);
