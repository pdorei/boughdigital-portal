import {
    Activity,
    BarChart3,
    BriefcaseBusiness,
    CheckCircle2,
    ClipboardList,
    Database,
    FileText,
    LayoutDashboard,
    Mail,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';

// `ability` hides links the user cannot open; the routes are authorized server-side.
export const WORKSPACE_NAV_ITEMS = [
    { slug: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { slug: 'clients', label: 'Clients', icon: BriefcaseBusiness, active: ['clients', 'client-detail', 'client-create', 'client-edit'] },
    { slug: 'tasks', label: 'Tasks', icon: CheckCircle2 },
    { slug: 'documents', label: 'Documents', icon: ClipboardList },
    { slug: 'team', label: 'Team', icon: Users, ability: 'team.manage', active: ['team', 'team-create', 'team-show', 'team-edit'] },
    { slug: 'activity', label: 'Activity', icon: Activity, ability: 'activity.view' },
    { slug: 'settings', label: 'Settings', icon: Settings },
    { type: 'section', label: 'Coming later' },
    { slug: 'content', label: 'Content', icon: FileText },
    { slug: 'seo', label: 'SEO', icon: ShieldCheck },
    { slug: 'outreach', label: 'Outreach', icon: Mail },
    { slug: 'reports', label: 'Reports', icon: BarChart3 },
];

export const ADMIN_NAV_ITEMS = [
    { slug: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { type: 'section', label: 'Platform' },
    { slug: 'workspaces', label: 'Workspaces', icon: BriefcaseBusiness, active: ['workspaces', 'workspace-create', 'workspace-show', 'workspace-edit'] },
    { slug: 'users', label: 'Users', icon: Users, active: ['users', 'user-create', 'user-show', 'user-edit'] },
    { slug: 'clients', label: 'Clients', icon: BriefcaseBusiness },
    { slug: 'documents', label: 'Documents', icon: ClipboardList },
    { slug: 'tasks', label: 'Tasks', icon: CheckCircle2 },
    { type: 'section', label: 'Services' },
    { slug: 'integrations', label: 'Integrations', icon: ShieldCheck },
    { slug: 'mcp', label: 'MCP', icon: Database },
    { type: 'section', label: 'Monitoring' },
    { slug: 'activity', label: 'Activity', icon: Activity },
    { type: 'section', label: 'Configuration' },
    { slug: 'system', label: 'System', icon: Settings },
];
