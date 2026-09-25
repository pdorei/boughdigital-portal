<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Requests\Clients\ClientRequest;
use App\Mcp\Tools\Concerns\SerializesModels;
use App\Models\Client;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_client')]
#[Description('Create a client in the active workspace.')]
class CreateClient extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, TenantContext $tenant)
    {
        $workspace = $tenant->workspace();
        $user = $request->user();
        abort_unless($user, 401);
        Gate::authorize('create', [Client::class, $workspace]);

        $data = Validator::make($request->all(), ClientRequest::fieldRules(creating: true))->validate();

        return Response::structured(['client' => $this->clientPayload($clients->create($workspace, $user, $data, 'mcp'))]);
    }
}
