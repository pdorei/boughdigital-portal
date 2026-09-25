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

    case TaskCreated = 'task.created';
    case TaskUpdated = 'task.updated';
    case TaskAssigned = 'task.assigned';
    case TaskStatusChanged = 'task.status_changed';
    case TaskDeleted = 'task.deleted';

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
