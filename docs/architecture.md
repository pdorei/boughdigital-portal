# Architecture

Bough Digital Portal is one Laravel application serving the portal, the portal API, the MCP endpoint and (later) queues and integrations. React renders the UI; business rules live in Laravel services.

Runtime paths share the same services:

- Portal pages: route -> `Pages\WorkspacePageController` / `Pages\AdminPageController` -> policy -> minimal page data. Lists load through the portal API.
- Portal API (`/{workspace}/api/...`, session + CSRF): controller -> form request -> policy -> service (`ClientService`, `TaskService`, `DocumentService`, `UserService`) -> API resource.
- MCP (`/mcp/{workspace}`, Sanctum token): tool -> validator (shared rules) -> policy -> the same services -> the same API resources.
- Jobs and future integrations must call services, not controllers or MCP tools.

The portal API is not an external API. A token-authenticated external REST API should be added under its own versioned prefix using `auth:sanctum` without the `web` middleware group.

Every mutation goes through a service that writes an `ActivityEvent` via `ActivityLogger` (sensitive keys are redacted recursively).
