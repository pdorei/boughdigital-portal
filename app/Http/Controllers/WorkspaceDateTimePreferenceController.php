<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\WorkspaceSettingsRequest;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\RedirectResponse;

class WorkspaceDateTimePreferenceController extends Controller
{
    public function update(WorkspaceSettingsRequest $request, WorkspaceService $workspaces): RedirectResponse
    {
        $workspaces->updateSettings($request->attributes->get('workspace'), $request->user(), $request->validated());

        return back()->with('status', 'Workspace date and time settings updated.');
    }
}
