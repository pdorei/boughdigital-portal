# Workspaces

Workspace URLs use `/{workspace}/...`, where `{workspace}` is `workspaces.slug`. The slug is a routing label only, never an authorization boundary or a storage key.

Middleware order for workspace routes:

1. `auth` (portal pages and the portal API) or `auth:sanctum` (MCP)
2. `workspace.resolve`
3. `workspace.access`

`ResolveWorkspace` loads an active workspace by slug into `TenantContext` and the request attributes. `EnsureWorkspaceAccess` rejects inactive users and users who do not belong to the workspace. Record IDs from the URL are always looked up inside the resolved workspace (`findInWorkspace`), so an ID from another workspace returns 404.

## Membership

A user belongs to at most one workspace (`users.workspace_id`); Super Admins have none. "Remove from workspace" clears `workspace_id` and client access but keeps the account and every record it owns. "Delete user" (Super Admin only) deletes the account; ownership columns on tasks and documents become null. A workspace must always keep one active admin.

## Lifecycle

Workspaces are activated/deactivated rather than deleted. Permanent deletion is only allowed for an inactive workspace with no clients, tasks or documents; members are detached and activity history is kept.

## Storage layout

Files are keyed by immutable IDs:

```
workspaces/{workspace_id}/logo/logo-<random>.<ext>                (public disk or S3)
workspaces/{workspace_id}/clients/{client_id}/documents/<uuid>.<ext>  (private disk or S3)
```

`workspaces.logo_disk` and `documents.storage_disk` record where each file lives, so switching the platform driver does not break existing files. Private documents are only served through `GET /{workspace}/api/documents/{id}/download`, which authorizes the request and streams the file (local) or redirects to a 5-minute signed URL (S3). `php artisan workspaces:migrate-storage` moves files from the old slug-based layout.

## Settings

`workspaces.settings` (JSONB) holds `timezone`, `date_format` and `datetime_format`. Updates only touch known keys, so unknown keys survive. Workspace admins may change these settings; name, slug and branding are Super Admin only.
