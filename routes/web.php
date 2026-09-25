<?php

use App\Http\Controllers\Admin\Api\PlatformController;
use App\Http\Controllers\Admin\Api\StorageSettingsController;
use App\Http\Controllers\Admin\Api\UserController as AdminUserController;
use App\Http\Controllers\Admin\Api\WorkspaceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Pages\AdminPageController;
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

        Route::redirect('/settings', '/admin/system')->name('admin.settings');
        Route::redirect('/settings/storage', '/admin/system')->name('admin.settings.storage');

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
