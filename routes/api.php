<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| Portal API: consumed by the React portal with the browser session and CSRF
| token. It is not a token-authenticated external API. Machine clients use MCP
| (/mcp/{workspace}, Sanctum tokens); a future external REST API should live
| under its own versioned prefix with auth:sanctum and without the web group.
*/
Route::prefix('{workspace}/api')
    ->middleware(['web', 'auth', 'workspace.resolve', 'workspace.access'])
    ->name('workspace.api.')
    ->group(function (): void {
        Route::apiResource('clients', ClientController::class)->where(['client' => '[0-9]+']);

        Route::apiResource('tasks', TaskController::class)->where(['task' => '[0-9]+']);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->whereNumber('task')->name('tasks.status');

        Route::apiResource('documents', DocumentController::class)->where(['document' => '[0-9]+']);
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->whereNumber('document')->name('documents.download');

        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');

        Route::apiResource('users', UserController::class)->except('destroy')->where(['user' => '[0-9]+']);
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->whereNumber('user')->name('users.activate');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->whereNumber('user')->name('users.deactivate');
        Route::delete('users/{user}/membership', [UserController::class, 'removeFromWorkspace'])->whereNumber('user')->name('users.remove');
    });
