<?php

use App\Http\Controllers\Admin\Api\PlatformConnectionController;
use App\Http\Controllers\Admin\Api\PlatformController;
use App\Http\Controllers\Admin\Api\StorageSettingsController;
use App\Http\Controllers\Admin\Api\Upwork\UpworkJobController;
use App\Http\Controllers\Admin\Api\Upwork\UpworkOverviewController;
use App\Http\Controllers\Admin\Api\Upwork\UpworkProposalController;
use App\Http\Controllers\Admin\Api\UpworkSettingsController;
use App\Http\Controllers\Admin\Api\UserController as AdminUserController;
use App\Http\Controllers\Admin\Api\WorkspaceController;
use App\Http\Controllers\Admin\UpworkOAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Pages\AdminPageController;
use App\Http\Controllers\Pages\UpworkPageController;
use App\Http\Controllers\Pages\WorkspacePageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDateTimePreferenceController;
use App\Http\Controllers\WorkspaceDateTimePreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'home']);

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

Route::middleware('guest')->group(function (): void {
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')
    ->middleware(['auth', 'can:create,App\Models\Workspace'])
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard');

        foreach (['dashboard', 'workspaces', 'users', 'clients', 'documents', 'tasks', 'activity', 'integrations', 'mcp', 'system', 'profile'] as $page) {
            Route::get("/{$page}", [AdminPageController::class, 'page'])->defaults('page', $page)->name("admin.{$page}");
        }

        // Global settings (file storage) and platform connections (Upwork OAuth, Claude key).
        Route::get('/settings', [AdminPageController::class, 'page'])->defaults('page', 'settings')
            ->middleware('can:manage-platform-settings')->name('admin.settings');
        Route::redirect('/settings/storage', '/admin/settings')->name('admin.settings.storage');
        Route::get('/connections', [AdminPageController::class, 'page'])->defaults('page', 'connections')
            ->middleware('can:manage-platform-settings')->name('admin.connections');

        // Upwork proposal automation (Super Admin only, no workspace).
        Route::middleware('can:manage-upwork')->group(function (): void {
            Route::redirect('/upwork', '/admin/upwork/overview');
            Route::redirect('/upwork/configuration', '/admin/upwork/automation');
            Route::get('/upwork/{page}', [UpworkPageController::class, 'page'])
                ->whereIn('page', ['overview', 'review', 'proposals', 'jobs', 'automation'])
                ->name('admin.upwork.page');

            Route::prefix('/api/upwork')->name('admin.api.upwork.')->group(function (): void {
                Route::get('/overview', [UpworkOverviewController::class, 'show'])->name('overview');
                Route::post('/runs', [UpworkOverviewController::class, 'run'])->middleware('throttle:6,1')->name('runs.store');

                Route::get('/jobs', [UpworkJobController::class, 'index'])->name('jobs.index');
                Route::get('/jobs/{upworkJob}', [UpworkJobController::class, 'show'])->whereNumber('upworkJob')->name('jobs.show');
                Route::post('/jobs/{upworkJob}/review', [UpworkJobController::class, 'review'])->whereNumber('upworkJob')->name('jobs.review');
                Route::post('/jobs/{upworkJob}/send-to-review', [UpworkJobController::class, 'sendToReview'])->whereNumber('upworkJob')->name('jobs.send-to-review');
                Route::post('/jobs/{upworkJob}/draft-proposal', [UpworkJobController::class, 'draftProposal'])->whereNumber('upworkJob')->name('jobs.draft-proposal');

                Route::get('/proposals', [UpworkProposalController::class, 'index'])->name('proposals.index');
                Route::get('/proposals/{upworkProposal}', [UpworkProposalController::class, 'show'])->whereNumber('upworkProposal')->name('proposals.show');
                Route::patch('/proposals/{upworkProposal}', [UpworkProposalController::class, 'update'])->whereNumber('upworkProposal')->name('proposals.update');
                Route::post('/proposals/{upworkProposal}/approve', [UpworkProposalController::class, 'approve'])->whereNumber('upworkProposal')->name('proposals.approve');
                Route::post('/proposals/{upworkProposal}/reject', [UpworkProposalController::class, 'reject'])->whereNumber('upworkProposal')->name('proposals.reject');
                Route::post('/proposals/{upworkProposal}/sent', [UpworkProposalController::class, 'markSent'])->whereNumber('upworkProposal')->name('proposals.sent');
            });
        });

        Route::middleware('can:manage-platform-settings')->group(function (): void {
            Route::get('/upwork/oauth/connect', [UpworkOAuthController::class, 'connect'])->name('admin.upwork.oauth.connect');
            Route::get('/upwork/oauth/callback', [UpworkOAuthController::class, 'callback'])->name('admin.upwork.oauth.callback');

            Route::get('/api/settings/upwork', [UpworkSettingsController::class, 'show'])->name('admin.api.settings.upwork.show');
            Route::patch('/api/settings/upwork', [UpworkSettingsController::class, 'update'])->name('admin.api.settings.upwork.update');
            Route::get('/api/connections', [PlatformConnectionController::class, 'index'])->name('admin.api.connections.index');
            Route::patch('/api/connections/anthropic', [PlatformConnectionController::class, 'updateAnthropic'])->name('admin.api.connections.anthropic');
            Route::post('/api/connections/{provider}/disconnect', [PlatformConnectionController::class, 'disconnect'])
                ->whereIn('provider', ['upwork', 'anthropic'])
                ->name('admin.api.connections.disconnect');
        });

        Route::get('/workspaces/create', [AdminPageController::class, 'createWorkspace'])->name('admin.workspaces.create');
        Route::get('/workspaces/{workspace}', [AdminPageController::class, 'showWorkspace'])->name('admin.workspaces.show');
        Route::get('/workspaces/{workspace}/edit', [AdminPageController::class, 'editWorkspace'])->name('admin.workspaces.edit');
        Route::get('/users/create', [AdminPageController::class, 'createUser'])->name('admin.users.create');
        Route::get('/users/{user}', [AdminPageController::class, 'showUser'])->name('admin.users.show');
        Route::get('/users/{user}/edit', [AdminPageController::class, 'editUser'])->name('admin.users.edit');

        Route::apiResource('/api/workspaces', WorkspaceController::class)->names('admin.api.workspaces');
        Route::post('/api/workspaces/{workspace}/activate', [WorkspaceController::class, 'activate'])->name('admin.api.workspaces.activate');
        Route::post('/api/workspaces/{workspace}/deactivate', [WorkspaceController::class, 'deactivate'])->name('admin.api.workspaces.deactivate');
        Route::apiResource('/api/users', AdminUserController::class)->names('admin.api.users');
        Route::post('/api/users/{user}/activate', [AdminUserController::class, 'activate'])->name('admin.api.users.activate');
        Route::post('/api/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.api.users.deactivate');
        Route::get('/api/clients', [PlatformController::class, 'clients'])->name('admin.api.clients');
        Route::get('/api/documents', [PlatformController::class, 'documents'])->name('admin.api.documents');
        Route::get('/api/tasks', [PlatformController::class, 'tasks'])->name('admin.api.tasks');
        Route::get('/api/integrations', [PlatformController::class, 'integrations'])->name('admin.api.integrations');
        Route::get('/api/mcp', [PlatformController::class, 'mcp'])->name('admin.api.mcp');
        Route::get('/api/activity', [PlatformController::class, 'activity'])->name('admin.api.activity');
        Route::get('/api/storage-settings', [StorageSettingsController::class, 'show'])->name('admin.api.storage-settings.show');
        Route::patch('/api/storage-settings', [StorageSettingsController::class, 'update'])->name('admin.api.storage-settings.update');
        Route::post('/api/storage-settings/test', [StorageSettingsController::class, 'test'])->name('admin.api.storage-settings.test');

        Route::post('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
        Route::post('/profile/date-time', [UserDateTimePreferenceController::class, 'update'])->name('admin.profile.date-time');
        Route::post('/password', [ProfileController::class, 'password'])->name('admin.password.update');
    });

Route::prefix('{workspace}')
    ->middleware(['auth', 'workspace.resolve', 'workspace.access'])
    ->name('workspace.')
    ->controller(WorkspacePageController::class)
    ->group(function (): void {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('clients', 'clients')->name('clients');
        Route::get('clients/create', 'createClient')->name('clients.create');
        Route::get('clients/{client}', 'showClient')->name('clients.show');
        Route::get('clients/{client}/edit', 'editClient')->name('clients.edit');
        Route::get('clients/{client}/articles/create', 'createArticle')->name('articles.create');
        Route::get('clients/{client}/articles/{article}', 'showArticle')->whereNumber('article')->name('articles.show');
        Route::get('clients/{client}/articles/{article}/edit', 'editArticle')->whereNumber('article')->name('articles.edit');
        Route::get('tasks', 'tasks')->name('tasks');
        Route::get('documents', 'documents')->name('documents');
        Route::get('activity', 'activity')->name('activity');
        Route::get('team', 'team')->name('team');
        Route::get('team/create', 'createMember')->name('team.create');
        Route::get('team/{user}', 'showMember')->whereNumber('user')->name('team.show');
        Route::get('team/{user}/edit', 'editMember')->whereNumber('user')->name('team.edit');
        Route::get('settings', 'settings')->name('settings');
        Route::get('profile', 'profile')->name('profile');
        Route::get('{module}', 'planned')->whereIn('module', ['content', 'seo', 'outreach', 'reports'])->name('planned');

        Route::post('settings/date-time', [WorkspaceDateTimePreferenceController::class, 'update'])->name('settings.date-time');
        Route::post('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/date-time', [UserDateTimePreferenceController::class, 'update'])->name('profile.date-time');
        Route::post('password', [ProfileController::class, 'password'])->name('password.update');
    });
