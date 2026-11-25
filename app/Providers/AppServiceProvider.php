<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Client;
use App\Models\ClientAiConfig;
use App\Models\ClientCompetitor;
use App\Models\ClientDocument;
use App\Models\ClientIntegration;
use App\Models\ContentCalendarEntry;
use App\Models\ContentPillar;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ArticlePolicy;
use App\Policies\ClientAiConfigPolicy;
use App\Policies\ClientCompetitorPolicy;
use App\Policies\ClientDocumentPolicy;
use App\Policies\ClientIntegrationPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ContentCalendarEntryPolicy;
use App\Policies\ContentPillarPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\Tenancy\TenantContext;
use App\View\Composers\PortalComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    public function boot(): void
    {
        View::composer('welcome', PortalComposer::class);

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(ClientDocument::class, ClientDocumentPolicy::class);
        Gate::policy(ClientCompetitor::class, ClientCompetitorPolicy::class);
        Gate::policy(ClientAiConfig::class, ClientAiConfigPolicy::class);
        Gate::policy(ClientIntegration::class, ClientIntegrationPolicy::class);
        Gate::policy(ContentPillar::class, ContentPillarPolicy::class);
        Gate::policy(ContentCalendarEntry::class, ContentCalendarEntryPolicy::class);
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
