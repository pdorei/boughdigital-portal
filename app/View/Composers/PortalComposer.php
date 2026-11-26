<?php

namespace App\View\Composers;

use App\Enums\ArticleScoreCategory;
use App\Enums\ArticleScoreType;
use App\Enums\ArticleStatus;
use App\Enums\ClientStatus;
use App\Enums\CompetitorTier;
use App\Enums\CompetitorType;
use App\Enums\ContentCalendarStatus;
use App\Enums\ContentType;
use App\Enums\DocumentFolder;
use App\Enums\DocumentSource;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Enums\SearchIntent;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UpworkJobStatus;
use App\Enums\UpworkJobType;
use App\Enums\UpworkProposalStatus;
use App\Enums\UpworkRunStatus;
use App\Enums\UpworkSearch;
use App\Enums\UpworkTrack;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DateTime\DateTimePreferenceService;
use App\Services\Upwork\UpworkOverviewService;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Builds window.__BOUGH_APP__, the bootstrap payload for the React shell.
 */
class PortalComposer
{
    public function __construct(private DateTimePreferenceService $dates) {}

    public function compose(View $view): void
    {
        $data = $view->getData();
        $user = request()->user();
        $workspace = $data['workspace'] ?? null;
        $businessWorkspace = $workspace ?? $user?->workspace;

        /** @var ViewErrorBag $errors */
        $errors = view()->shared('errors', new ViewErrorBag);

        $view->with('boughPayload', [
            'page' => $data['page'] ?? 'welcome',
            'section' => $data['section'] ?? null,
            'user' => $user ? $this->user($user) : null,
            'workspace' => $workspace ? $this->workspace($workspace) : null,
            'abilities' => $user ? $this->abilities($user, $workspace, $data['section'] ?? null) : [],
            'navBadges' => $user && ($data['section'] ?? null) === 'admin' && $user->can('manage-upwork') ? $this->adminBadges() : [],
            'options' => $this->options(),
            'datePreferences' => $this->dates->resolveWithSources($user, $workspace),
            'datePreferenceOptions' => $this->dates->options($user, $workspace),
            'workspaceDatePreferences' => $this->dates->workspaceRawPreferences($businessWorkspace),
            'workspaceBusinessDatePreferences' => $this->dates->workspaceBusinessPreferences($businessWorkspace),
            'userDatePreferences' => $this->dates->userRawPreferences($user),
            'csrf' => csrf_token(),
            'errors' => $errors->toArray(),
            'old' => session()->getOldInput(),
            'status' => session('status'),
            'resetToken' => $data['resetToken'] ?? null,
            'resetEmail' => $data['resetEmail'] ?? null,
            'pageData' => $data['pageData'] ?? [],
        ]);
    }

    private function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'avatar_url' => $user->avatar_url,
            'timezone' => $user->timezone,
            'date_format' => $user->date_format,
            'datetime_format' => $user->datetime_format,
            'workspace' => $user->workspace ? $this->workspace($user->workspace) : null,
        ];
    }

    private function workspace(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'logo_url' => $workspace->logo_url,
            'primary_colour' => $workspace->primary_colour,
            'accent_colour' => $workspace->accent_colour,
        ];
    }

    /**
     * Page-level abilities for showing or hiding controls. The server still
     * authorizes every request; these flags only shape the UI.
     */
    private function abilities(User $user, ?Workspace $workspace, ?string $section): array
    {
        if ($section === 'admin') {
            return [
                '*' => $user->isSuperAdmin(),
                'upwork.manage' => $user->can('manage-upwork'),
                'settings.manage' => $user->can('manage-platform-settings'),
            ];
        }

        if (! $workspace) {
            return [];
        }

        return [
            'clients.create' => $user->can('create', [Client::class, $workspace]),
            'tasks.create' => $user->can('create', [Task::class, $workspace]),
            'documents.create' => $user->role->canManageTasks(),
            'team.manage' => $user->can('viewAny', [User::class, $workspace]),
            'settings.update' => $user->can('updateSettings', $workspace),
            'activity.view' => $user->can('viewActivity', $workspace),
        ];
    }

    /**
     * Sidebar counts, computed here so React never derives them.
     *
     * @return array<string, int>
     */
    private function adminBadges(): array
    {
        $badges = app(UpworkOverviewService::class)->badges();

        return ['upwork-review' => $badges['review'], 'upwork-proposals' => $badges['proposals']];
    }

    private function options(): array
    {
        return [
            'client_statuses' => ClientStatus::options(),
            'task_statuses' => TaskStatus::options(),
            'task_priorities' => TaskPriority::options(),
            'document_folders' => DocumentFolder::options(),
            'document_sources' => DocumentSource::options(),
            'competitor_tiers' => CompetitorTier::options(),
            'competitor_types' => CompetitorType::options(),
            'integration_providers' => IntegrationProvider::options(),
            'integration_auth_types' => IntegrationAuthType::options(),
            'integration_statuses' => IntegrationStatus::options(),
            'content_calendar_statuses' => ContentCalendarStatus::options(),
            'content_types' => ContentType::options(),
            'search_intents' => SearchIntent::options(),
            'article_statuses' => ArticleStatus::options(),
            'article_score_types' => ArticleScoreType::options(),
            'article_score_categories' => ArticleScoreCategory::options(),
            'upwork_job_statuses' => UpworkJobStatus::options(),
            'upwork_proposal_statuses' => UpworkProposalStatus::options(),
            'upwork_tracks' => UpworkTrack::options(),
            'upwork_job_types' => UpworkJobType::options(),
            'upwork_searches' => UpworkSearch::options(),
            'upwork_run_statuses' => UpworkRunStatus::options(),
            'roles' => UserRole::options(),
            'workspace_roles' => array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()], UserRole::workspaceRoles()),
        ];
    }
}
