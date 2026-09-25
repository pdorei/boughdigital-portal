<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\ActivityLog;
use App\Services\Clients\ClientService;
use App\Support\Pagination;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function __construct(private ClientService $clients, private TenantContext $tenant) {}

    /**
     * Workspace-wide history is for workspace admins; a client's history is
     * visible to anyone who can view that client.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $this->tenant->workspace();
        $client = $request->filled('client_id')
            ? $this->clients->findInWorkspace($workspace, $request->integer('client_id'))
            : null;

        $client ? $this->authorize('view', $client) : $this->authorize('viewActivity', $workspace);

        $query = ActivityLog::query()
            ->with(['user:id,name', 'client:id,name,slug'])
            ->whereBelongsTo($workspace)
            ->when($client, fn ($query) => $query->whereBelongsTo($client))
            ->latest('created_at')
            ->latest('id');

        return ActivityResource::collection(Pagination::paginate($query, $request->only(['page', 'per_page'])));
    }
}
