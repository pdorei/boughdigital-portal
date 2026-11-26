import React from 'react';
import AdminActivity from '@/pages/admin/Activity';
import AdminClients from '@/pages/admin/Clients';
import AdminDashboard from '@/pages/admin/Dashboard';
import AdminDocuments from '@/pages/admin/Documents';
import AdminIntegrations from '@/pages/admin/Integrations';
import AdminMcp from '@/pages/admin/Mcp';
import AdminConnections from '@/pages/admin/connections/Index';
import AdminSettings from '@/pages/admin/settings/Index';
import AdminSystem from '@/pages/admin/System';
import UpworkAutomation from '@/pages/admin/upwork/Automation';
import UpworkJobs from '@/pages/admin/upwork/Jobs';
import UpworkOverview from '@/pages/admin/upwork/Overview';
import UpworkProposals from '@/pages/admin/upwork/Proposals';
import UpworkReview from '@/pages/admin/upwork/Review';
import AdminTasks from '@/pages/admin/Tasks';
import AdminUserCreate from '@/pages/admin/users/Create';
import AdminUserEdit from '@/pages/admin/users/Edit';
import AdminUsersIndex from '@/pages/admin/users/Index';
import UserShow from '@/pages/admin/users/Show';
import WorkspaceCreate from '@/pages/admin/workspaces/Create';
import WorkspaceEdit from '@/pages/admin/workspaces/Edit';
import WorkspaceIndex from '@/pages/admin/workspaces/Index';
import WorkspaceShow from '@/pages/admin/workspaces/Show';
import ActivityIndex from '@/pages/activity/Index';
import ForgotPassword from '@/pages/auth/ForgotPassword';
import Login from '@/pages/auth/Login';
import ResetPassword from '@/pages/auth/ResetPassword';
import ClientCreate from '@/pages/clients/Create';
import ClientEdit from '@/pages/clients/Edit';
import ClientsIndex from '@/pages/clients/Index';
import ClientShow from '@/pages/clients/Show';
import ArticleCreate from '@/pages/content/articles/Create';
import ArticleEdit from '@/pages/content/articles/Edit';
import ArticleShow from '@/pages/content/articles/Show';
import Dashboard from '@/pages/dashboard/Dashboard';
import DocumentsIndex from '@/pages/documents/Index';
import GenericPage from '@/pages/generic/GenericPage';
import Profile from '@/pages/profile/Profile';
import SettingsIndex from '@/pages/settings/Index';
import TasksIndex from '@/pages/tasks/Index';
import TeamCreate from '@/pages/team/Create';
import TeamEdit from '@/pages/team/Edit';
import TeamIndex from '@/pages/team/Index';
import TeamShow from '@/pages/team/Show';
import AdminLayout from '@/layouts/AdminLayout';
import WorkspaceLayout from '@/layouts/WorkspaceLayout';
import { useWorkspace } from '@/hooks/useWorkspace';
import { labelize } from '@/utils/text';

function AdminProfile() {
    return <Profile base="/admin" />;
}

const ADMIN_PAGES = {
    dashboard: AdminDashboard,
    workspaces: WorkspaceIndex,
    'workspace-create': WorkspaceCreate,
    'workspace-show': WorkspaceShow,
    'workspace-edit': WorkspaceEdit,
    users: AdminUsersIndex,
    'user-create': AdminUserCreate,
    'user-show': UserShow,
    'user-edit': AdminUserEdit,
    clients: AdminClients,
    documents: AdminDocuments,
    tasks: AdminTasks,
    integrations: AdminIntegrations,
    mcp: AdminMcp,
    activity: AdminActivity,
    system: AdminSystem,
    settings: AdminSettings,
    connections: AdminConnections,
    'upwork-overview': UpworkOverview,
    'upwork-review': UpworkReview,
    'upwork-proposals': UpworkProposals,
    'upwork-jobs': UpworkJobs,
    'upwork-automation': UpworkAutomation,
    profile: AdminProfile,
};

const WORKSPACE_PAGES = {
    dashboard: Dashboard,
    clients: ClientsIndex,
    'client-detail': ClientShow,
    'client-create': ClientCreate,
    'client-edit': ClientEdit,
    'article-create': ArticleCreate,
    'article-show': ArticleShow,
    'article-edit': ArticleEdit,
    tasks: TasksIndex,
    documents: DocumentsIndex,
    activity: ActivityIndex,
    settings: SettingsIndex,
    profile: Profile,
};

const TEAM_PAGES = {
    team: TeamIndex,
    'team-create': TeamCreate,
    'team-show': TeamShow,
    'team-edit': TeamEdit,
};

function titleFor(page, section, pageData) {
    const titles = {
        'client-detail': pageData.client?.name,
        'client-create': 'Add client',
        'client-edit': `Edit ${pageData.client?.name}`,
        'article-create': 'New article',
        'article-show': pageData.article?.title,
        'article-edit': `Edit ${pageData.article?.title}`,
        'workspace-create': 'Create workspace',
        'workspace-show': pageData.workspace?.name,
        'workspace-edit': `Edit ${pageData.workspace?.name}`,
        'user-create': 'Create user',
        'user-show': pageData.selected_user?.name,
        'user-edit': `Edit ${pageData.selected_user?.name}`,
        'team-create': 'Add team member',
        'team-show': pageData.selected_user?.name,
        'team-edit': `Edit ${pageData.selected_user?.name}`,
        'upwork-overview': 'Upwork overview',
        'upwork-review': 'Upwork review',
        'upwork-proposals': 'Upwork proposals',
        'upwork-jobs': 'Upwork jobs',
        'upwork-automation': 'Upwork automation',
    };

    if (section === 'admin' && page === 'dashboard') return 'Platform dashboard';

    return titles[page] || labelize(page || 'dashboard');
}

export default function Router() {
    const { page, pageData, section, workspaceSlug } = useWorkspace();

    if (page === 'login') return <Login />;
    if (page === 'forgot-password') return <ForgotPassword />;
    if (page === 'reset-password') return <ResetPassword />;

    const title = titleFor(page, section, pageData);

    if (section === 'admin') {
        const Page = ADMIN_PAGES[page] || GenericPage;

        return (
            <AdminLayout title={title}>
                <Page title={title} />
            </AdminLayout>
        );
    }

    const base = `/${workspaceSlug}`;
    const TeamPage = TEAM_PAGES[page];
    const Page = WORKSPACE_PAGES[page] || GenericPage;

    return (
        <WorkspaceLayout title={title}>
            {TeamPage ? <TeamPage base={`${base}/team`} /> : <Page title={title} base={base} />}
        </WorkspaceLayout>
    );
}
