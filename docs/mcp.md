# MCP

Endpoint:

`/mcp/{workspace}`

Authentication:

The endpoint uses `auth:sanctum`, so clients must send a valid bearer token for a user who can access the workspace slug. OAuth can be added later if an MCP client requires it. The workspace slug is resolved only after the fixed `/mcp` namespace and inactive or missing workspaces fail without falling back to a default workspace.

Initial tools:

- `list_clients`
- `search_clients`
- `get_client`
- `create_client`
- `update_client`
- `list_client_documents`
- `link_google_doc`
- `list_tasks`

Each tool validates arguments, uses `TenantContext`, authorizes through Laravel policies, calls a domain service, and returns structured content. No generic SQL, shell, file, or arbitrary HTTP tools are exposed.

To add a tool, create a thin class under `app/Mcp/Tools/{Domain}`, add validation and policy checks, call an existing service method, and register it in `WorkspaceServer`.

List tools accept `page` and `per_page` (capped at 100). `link_google_doc` stores a Google Doc link record (it does not call Google APIs); `folder` is one of `seo`, `strategy`, `content`, `reports`, `other`.
