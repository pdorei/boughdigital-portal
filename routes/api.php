<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ArticleScoreController;
use App\Http\Controllers\Api\ClientAiConfigController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientIntegrationController;
use App\Http\Controllers\Api\CompetitorController;
use App\Http\Controllers\Api\ContentCalendarController;
use App\Http\Controllers\Api\ContentPillarController;
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

        Route::apiResource('clients.competitors', CompetitorController::class)->where(['client' => '[0-9]+', 'competitor' => '[0-9]+']);
        Route::controller(CompetitorController::class)->prefix('clients/{client}/competitors/{competitor}/logo')->whereNumber(['client', 'competitor'])->group(function (): void {
            Route::get('/', 'logo')->name('clients.competitors.logo');
            Route::post('/', 'uploadLogo')->name('clients.competitors.logo.store');
            Route::delete('/', 'removeLogo')->name('clients.competitors.logo.destroy');
        });

        Route::apiResource('clients.integrations', ClientIntegrationController::class)->where(['client' => '[0-9]+', 'integration' => '[0-9]+']);
        Route::post('clients/{client}/integrations/{integration}/disconnect', [ClientIntegrationController::class, 'disconnect'])
            ->whereNumber(['client', 'integration'])
            ->name('clients.integrations.disconnect');

        Route::apiResource('clients.content-pillars', ContentPillarController::class)
            ->parameters(['content-pillars' => 'pillar'])
            ->where(['client' => '[0-9]+', 'pillar' => '[0-9]+']);
        Route::apiResource('clients.content-calendar', ContentCalendarController::class)
            ->parameters(['content-calendar' => 'entry'])
            ->where(['client' => '[0-9]+', 'entry' => '[0-9]+']);
        Route::apiResource('clients.articles', ArticleController::class)->where(['client' => '[0-9]+', 'article' => '[0-9]+']);
        Route::apiResource('clients.articles.scores', ArticleScoreController::class)
            ->only(['index', 'store', 'show'])
            ->where(['client' => '[0-9]+', 'article' => '[0-9]+', 'score' => '[0-9]+']);

        Route::controller(ClientAiConfigController::class)->prefix('clients/{client}/ai-config')->whereNumber('client')->group(function (): void {
            Route::get('/', 'show')->name('clients.ai-config.show');
            Route::put('/', 'update')->name('clients.ai-config.update');
            Route::delete('/', 'destroy')->name('clients.ai-config.destroy');
        });

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
