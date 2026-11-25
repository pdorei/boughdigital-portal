<?php

namespace App\Support\Routing;

/**
 * Slugs that would collide with top-level routes (/{slug}/...) or public paths.
 */
class ReservedWorkspaceSlugs
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'activity',
            'admin',
            'api',
            'build',
            'clients',
            'content',
            'dashboard',
            'documents',
            'forgot-password',
            'horizon',
            'install',
            'login',
            'logout',
            'mcp',
            'outreach',
            'password',
            'profile',
            'reports',
            'reset-password',
            'sanctum',
            'seo',
            'settings',
            'storage',
            'tasks',
            'team',
            'up',
            'users',
        ];
    }
}
