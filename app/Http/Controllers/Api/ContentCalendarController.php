<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesWorkspaceClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CalendarEntryRequest;
use App\Http\Resources\ContentCalendarEntryResource;
use App\Models\ContentCalendarEntry;
use App\Services\Content\ContentCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContentCalendarController extends Controller
{
    use ResolvesWorkspaceClient;

    public function __construct(private ContentCalendarService $calendar) {}

    public function index(Request $request, string $workspace, int $client): AnonymousResourceCollection
    {
        $client = $this->workspaceClient($client);
        $this->authorize('viewAny', [ContentCalendarEntry::class, $client]);

        $filters = $request->only(['search', 'status', 'writer', 'pillar', 'from', 'to', 'page', 'per_page']);

        return ContentCalendarEntryResource::collection($this->calendar->list($client, $request->user(), $filters))->additional(['meta' => [
            'can_create' => $request->user()->can('create', [ContentCalendarEntry::class, $client]),
        ]]);
    }

    public function store(CalendarEntryRequest $request, string $workspace, int $client): ContentCalendarEntryResource
    {
        $client = $this->workspaceClient($client);
        $this->authorize('create', [ContentCalendarEntry::class, $client]);

        return new ContentCalendarEntryResource($this->calendar->create($client, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $entry): ContentCalendarEntryResource
    {
        return new ContentCalendarEntryResource($this->entry('view', $client, $entry));
    }

    public function update(CalendarEntryRequest $request, string $workspace, int $client, int $entry): ContentCalendarEntryResource
    {
        $entry = $this->entry('update', $client, $entry);

        return new ContentCalendarEntryResource($this->calendar->update($entry, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, string $workspace, int $client, int $entry): Response
    {
        $this->calendar->delete($this->entry('delete', $client, $entry), $request->user());

        return response()->noContent();
    }

    private function entry(string $ability, int $clientId, int $id): ContentCalendarEntry
    {
        $entry = $this->calendar->findForClient($this->workspaceClient($clientId), $id);
        $this->authorize($ability, $entry);

        return $entry;
    }
}
