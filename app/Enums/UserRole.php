<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case TeamLeader = 'team_leader';
    case Employee = 'employee';
    case Writer = 'writer';
    case Va = 'va';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Workspace Admin',
            self::TeamLeader => 'Team Leader',
            self::Employee => 'Employee',
            self::Writer => 'Writer',
            self::Va => 'VA',
        };
    }

    /**
     * Roles a workspace can assign to its members.
     *
     * @return array<int, self>
     */
    public static function workspaceRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::SuperAdmin));
    }

    public function canManageWorkspace(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function canManageTasks(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::TeamLeader], true);
    }

    public function canAccessAllWorkspaceClients(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }
}
