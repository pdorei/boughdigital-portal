<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Requests\Clients\ClientRequest;
use App\Mcp\Tools\Concerns\SerializesModels;
use App\Services\Clients\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_client')]
#[Description('Update a client in the active workspace.')]
class UpdateClient extends Tool
{
    use SerializesModels;

    public function handle(Request $request, ClientService $clients, TenantContext $tenant)
    {
        $data = Validator::make($request->all(), [
            'client_id' => ['required', 'integer'],
            ...ClientRequest::fieldRules(creating: false),
        ])->validate();

        $client = $clients->findInWorkspace($tenant->workspace(), (int) $data['client_id']);
        Gate::authorize('update', $client);

        unset($data['client_id']);

        return Response::structured(['client' => $this->clientPayload($clients->update($client, $request->user(), $data, 'mcp'))]);
    }
}
