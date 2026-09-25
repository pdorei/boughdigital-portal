# Database

Initial schema corrections applied:

- `workspaces.slug` is unique and active/inactive state is indexed.
- `users.workspace_id` is nullable only for platform users such as `super_admin`.
- Workspace data tables include `workspace_id` where it materially improves isolation and query performance.
- Structured JSON data uses PostgreSQL `jsonb` columns where the Phase 1 schema stores settings, metadata, and audit details.
- Client child tables use foreign keys to `clients`; services and policies verify the parent client belongs to the active workspace.
- `documents` was added as the client document library requested by the brief.
- `activity_log` includes `source`, `ip_address`, `user_agent`, and `request_id` while avoiding secret fields.
- Token authentication uses Sanctum `personal_access_tokens`.

Migration order:

1. Workspaces, users, auth/session tables.
2. Personal access tokens.
3. Clients, client children, documents, task/time tracking, client access, audit log.

Later phases should add content, SEO, reporting, outreach, integration, and queue-specific tables in dependency order after this foundation is green.
