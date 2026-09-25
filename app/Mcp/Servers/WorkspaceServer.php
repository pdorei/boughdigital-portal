<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Clients\CreateClient;
use App\Mcp\Tools\Clients\GetClient;
use App\Mcp\Tools\Clients\ListClients;
use App\Mcp\Tools\Clients\SearchClients;
use App\Mcp\Tools\Clients\UpdateClient;
use App\Mcp\Tools\Documents\LinkGoogleDoc;
use App\Mcp\Tools\Documents\ListClientDocuments;
use App\Mcp\Tools\Tasks\ListTasks;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Bough Digital Workspace')]
#[Version('0.1.0')]
#[Instructions('Workspace-scoped tools for Bough Digital. Every request is authenticated, workspace-resolved, authorized, and routed through Laravel services.')]
class WorkspaceServer extends Server
{
    protected array $tools = [
        ListClients::class,
        SearchClients::class,
        GetClient::class,
        CreateClient::class,
        UpdateClient::class,
        ListClientDocuments::class,
        LinkGoogleDoc::class,
        ListTasks::class,
    ];
}
