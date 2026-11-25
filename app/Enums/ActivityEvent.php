<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ActivityEvent: string
{
    case UserLogin = 'user.login';
    case UserProfileUpdated = 'user.profile_updated';
    case UserPasswordChanged = 'user.password_changed';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserDeleted = 'user.deleted';

    case WorkspaceCreated = 'workspace.created';
    case WorkspaceUpdated = 'workspace.updated';
    case WorkspaceSlugChanged = 'workspace.slug_changed';
    case WorkspaceSettingsUpdated = 'workspace.settings_updated';
    case WorkspaceActivated = 'workspace.activated';
    case WorkspaceDeactivated = 'workspace.deactivated';
    case WorkspaceDeleted = 'workspace.deleted';
    case WorkspaceLogoUploaded = 'workspace.logo_uploaded';
    case WorkspaceLogoReplaced = 'workspace.logo_replaced';
    case WorkspaceLogoRemoved = 'workspace.logo_removed';
    case WorkspaceMemberAdded = 'workspace.member_added';
    case WorkspaceMemberRemoved = 'workspace.member_removed';
    case WorkspaceMemberRoleChanged = 'workspace.member_role_changed';

    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';
    case ClientArchived = 'client.archived';
    case ClientRestored = 'client.restored';
    case ClientDeleted = 'client.deleted';
    case ClientAiConfigUpdated = 'client.ai_config_updated';
    case ClientAiConfigReset = 'client.ai_config_reset';

    case CompetitorCreated = 'competitor.created';
    case CompetitorUpdated = 'competitor.updated';
    case CompetitorDeleted = 'competitor.deleted';

    case TaskCreated = 'task.created';
    case TaskUpdated = 'task.updated';
    case TaskAssigned = 'task.assigned';
    case TaskStatusChanged = 'task.status_changed';
    case TaskDeleted = 'task.deleted';

    case IntegrationConnected = 'integration.connected';
    case IntegrationUpdated = 'integration.updated';
    case IntegrationDisconnected = 'integration.disconnected';
    case IntegrationDeleted = 'integration.deleted';

    case ContentPillarCreated = 'content_pillar.created';
    case ContentPillarUpdated = 'content_pillar.updated';
    case ContentPillarDeleted = 'content_pillar.deleted';
    case CalendarEntryCreated = 'content_calendar.created';
    case CalendarEntryUpdated = 'content_calendar.updated';
    case CalendarEntryDeleted = 'content_calendar.deleted';
    case ArticleCreated = 'article.created';
    case ArticleUpdated = 'article.updated';
    case ArticleDeleted = 'article.deleted';
    case ArticleScored = 'article.scored';

    case DocumentUploaded = 'document.uploaded';
    case DocumentLinked = 'document.linked';
    case DocumentUpdated = 'document.updated';
    case DocumentDeleted = 'document.deleted';

    public function label(): string
    {
        return match ($this) {
            self::UserLogin => 'Signed in',
            self::UserProfileUpdated => 'Updated their profile',
            self::UserPasswordChanged => 'Changed their password',
            self::WorkspaceMemberAdded => 'Added a team member',
            self::WorkspaceMemberRemoved => 'Removed a team member',
            self::WorkspaceMemberRoleChanged => 'Changed a team member role',
            self::TaskStatusChanged => 'Changed task status',
            self::CalendarEntryCreated => 'Added a calendar entry',
            self::CalendarEntryUpdated => 'Updated a calendar entry',
            self::CalendarEntryDeleted => 'Deleted a calendar entry',
            self::ArticleScored => 'Scored an article',
            self::ClientAiConfigUpdated => 'Updated AI configuration',
            self::ClientAiConfigReset => 'Reset AI configuration',
            default => Str::of($this->value)->replace(['.', '_'], ' ')->ucfirst()->toString(),
        };
    }

    /**
     * Rows written before this catalogue existed keep their raw action string.
     */
    public static function labelFor(string $action): string
    {
        return self::tryFrom($action)?->label()
            ?? Str::of($action)->replace(['.', '_'], ' ')->ucfirst()->toString();
    }
}
