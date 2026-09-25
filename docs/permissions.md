# Permissions

Roles are `App\Enums\UserRole`. Capabilities are expressed as enum methods used by the policies:

| Role | Workspace (`canManageWorkspace`) | Tasks (`canManageTasks`) | All clients (`canAccessAllWorkspaceClients`) |
|---|---|---|---|
| super_admin | yes (every workspace) | yes | yes |
| admin | yes (own workspace) | yes | yes |
| team_leader | no | yes, for permitted clients | no |
| employee, writer, va | no | no (may change status of tasks assigned to them) | no |

- Non-admins only see clients attached through `user_client_access`, plus tasks assigned to or created by them. The same rules are query scopes (`Client::visibleTo`, `Task::visibleTo`, `ClientDocument::visibleTo`) so lists, pages, API and MCP agree.
- Workspace admins cannot change another admin's email or password, their own role/status, or leave a workspace without an admin.
- Only Super Admins manage the workspace entity, delete platform accounts and open `/admin`.

React receives page-level `abilities` and per-record `can` flags computed by these policies. They only decide which controls are shown; every request is authorized again on the server.
