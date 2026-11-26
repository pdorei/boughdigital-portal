<?php

namespace App\Http\Controllers\Pages;

use Illuminate\View\View;

/**
 * Upwork pages are rendered empty and load their data through the admin
 * Upwork API. Every route is behind the Super Admin gate and manage-upwork.
 */
class UpworkPageController extends PortalPageController
{
    public function page(string $page): View
    {
        $this->authorize('manage-upwork');

        return $this->render("upwork-{$page}", 'admin');
    }
}
