<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\View\View;

/**
 * Renders the React shell. Each page action authorizes first and passes only
 * the data that page needs; lists are loaded through the paginated portal API.
 */
abstract class PortalPageController extends Controller
{
    protected function render(string $page, string $section, array $pageData = [], ?Workspace $workspace = null): View
    {
        return view('welcome', [
            'page' => $page,
            'section' => $section,
            'workspace' => $workspace,
            'pageData' => $pageData,
        ]);
    }
}
