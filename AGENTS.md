# AGENTS.md — Bough Digital Portal

## Project Overview

Bough Digital Portal is a multi-workspace Laravel application that provides:

- Platform administration
- Workspace administration
- Client management
- User/team management
- Tasks and workflows
- Documents
- Content workflows
- SEO workflows
- Outreach workflows
- Reports
- Workspace-specific settings
- File storage
- REST API
- MCP Server integration
- Background automation

The platform is designed so Laravel, React, REST APIs, MCP tools, background jobs, and future integrations all operate on the same underlying business data and service layer.

The primary tenancy boundary is the **Workspace**.

---

# Technology Stack

Backend:

- Laravel 13
- PHP 8.5+
- PostgreSQL 17
- Laravel Sanctum
- Laravel MCP
- Laravel Horizon
- Redis / queues where required

Frontend:

- React
- Vite
- Tailwind CSS
- Lucide or the existing project icon library
- Laravel remains responsible for server-side routing/auth/bootstrap

Infrastructure / integrations:

- PostgreSQL
- Redis
- Laravel Filesystem
- AWS S3 planned for production storage
- Google APIs planned
- Semrush API planned
- HubSpot planned
- Screaming Frog workflows planned
- MCP clients such as Claude

Do not introduce another framework or major dependency unless it is genuinely required.

---

# Local Project

Current local project:

    /Users/mac/Websites/BoughDigital/portal

Web document root:

    public_html/

The hosting environment only serves `public_html`, so Laravel's default `public/` directory has been renamed. The public path is set in `bootstrap/app.php` (`usePublicPath`) and `vite.config.js` (`publicDirectory`). Always use `public_path()` rather than hardcoding `public/`.

Local application URL:

    http://portal.local

Canonical development database:

    PostgreSQL

Database:

    boughdigital

Never assume MySQL or SQLite is the canonical development database.

---

# Critical Database Safety Rule

The PostgreSQL `boughdigital` database contains development data that must be preserved.

NEVER run:

    php artisan migrate:fresh
    php artisan migrate:refresh
    php artisan db:wipe

Do not drop tables.

Do not reset the database.

Do not delete development data merely to make tests pass.

Normal schema changes must use incremental migrations:

    php artisan migrate

Before running any destructive database operation, STOP.

---

# Testing Database Safety

Never run tests using `RefreshDatabase`, `DatabaseMigrations`, destructive seed/reset behavior, or equivalent against:

    DB_DATABASE=boughdigital

Automated database tests must use an isolated testing database.

Preferred:

    boughdigital_testing

Before running tests that mutate/reset the database, inspect the testing environment and confirm it is NOT using the development database.

Current safeguards (do not remove or weaken them):

- `phpunit.xml` forces `DB_DATABASE=boughdigital_testing` (`force="true"`).
- `tests/TestCase.php` refuses to boot when the configured database name does not end in `_testing` (this also catches a cached config pointing at `boughdigital`).

If isolation cannot be confirmed:

    DO NOT RUN THE DATABASE TEST SUITE.

Report:

    Tests: NOT RUN — isolated test database not confirmed

instead.

Preserving development data is more important than reporting a passing test suite.

---

# Architecture

The system should follow:

    React UI
        ↓
    Laravel routes / REST API
        ↓
    Controllers
        ↓
    Authorization
        ↓
    Services
        ↓
    Models / integrations
        ↓
    PostgreSQL / S3 / external APIs

MCP must reuse the same business/service layer where practical:

    Claude
       ↓
    MCP Tool
       ↓
    Laravel Service
       ↓
    Model / Integration
       ↓
    PostgreSQL

Do not create separate business logic for:

- React
- REST API
- MCP

when the same operation can be implemented in a shared service.

---

# Multi-Workspace Architecture

Workspace is the primary tenancy boundary.

Typical workspace routes:

    /{workspace}/dashboard
    /{workspace}/clients
    /{workspace}/tasks
    /{workspace}/documents
    /{workspace}/content
    /{workspace}/seo
    /{workspace}/reports
    /{workspace}/settings

Portal API (session + CSRF, used by React):

    /{workspace}/api/...

MCP (Sanctum bearer token):

    /mcp/{workspace}

The MCP path is deliberately `/mcp/{workspace}`; a test asserts that `/{workspace}/mcp` is not registered.

Membership model: a user belongs to at most one workspace (`users.workspace_id`). Super Admins have no workspace. Client-level access within a workspace is `user_client_access`.

Never trust the workspace slug alone for authorization.

Always resolve the workspace and verify the authenticated user has permission to access it.

Cross-workspace data leakage is a critical security defect.

---

# Roles

Current conceptual roles include:

- Super Admin
- Admin
- Team Leader
- Employee
- Writer
- VA

Exact role names in the code/database are authoritative.

Do not invent new roles without checking the existing role implementation.

---

# Super Admin

Super Admin is platform-level.

Super Admin can manage:

- Workspaces
- Platform users
- Workspace users where appropriate
- Platform configuration
- All normal workspace business data when authorized by the existing architecture

Super Admin routes include:

    /admin/...

Only Super Admin may manage Workspace entities themselves unless an explicit policy says otherwise.

---

# Workspace Admin

Workspace Admin can manage data inside their workspace.

Examples:

- Clients
- Team
- Tasks
- Documents
- Content
- SEO
- Reports
- Workspace settings

Workspace Admin may change explicitly editable workspace settings (currently timezone and date formats, via `WorkspacePolicy::updateSettings`). Workspace name, slug, branding and lifecycle are Super Admin only.

Workspace Admin must NOT manage the platform's Workspace registry.

For example, Workspace Admin must not gain access to:

    /admin/workspaces

merely because they are an admin.

---

# Authorization

Frontend permission checks are for UX only.

They are NOT security.

Every sensitive operation must also be protected server-side using:

- middleware
- policies
- gates
- service authorization where appropriate

Never rely only on hiding a React button.

React receives page-level `abilities` (bootstrap payload) and per-record `can` flags (API resources), both computed by the policies. Do not recreate role-to-permission maps in JavaScript. Select options for statuses, priorities, folders and roles come from the PHP enums via the bootstrap `options` payload.

---

# React Frontend Architecture

Keep `resources/js/app.jsx` bootstrap-only.

Preferred architecture:

    resources/js/
    ├── app.jsx
    ├── router/
    │   └── index.jsx
    ├── layouts/
    ├── pages/
    ├── components/
    │   ├── ui/
    │   ├── forms/
    │   └── layout/
    ├── api/
    ├── hooks/
    ├── context/
    ├── utils/
    └── constants/

Do not rebuild the application into one large `app.jsx`.

---

# Pages

Feature-specific UI belongs under:

    resources/js/pages/

Examples:

    pages/admin/workspaces/
    pages/admin/users/
    pages/clients/
    pages/tasks/
    pages/documents/
    pages/content/
    pages/seo/
    pages/outreach/
    pages/reports/
    pages/team/
    pages/settings/

Large pages should be decomposed into feature components.

Example:

    pages/clients/
    ├── Index.jsx
    ├── Create.jsx
    ├── Edit.jsx
    ├── Show.jsx
    └── components/
        ├── ClientForm.jsx
        ├── ClientTable.jsx
        └── ClientTabs.jsx

Do not duplicate feature-specific components globally unless they are genuinely reusable.

---

# Shared UI Components

Reusable UI belongs under:

    resources/js/components/ui/

Examples:

- Button
- Input
- Select
- Textarea
- Checkbox
- Badge
- Modal
- Drawer
- Dropdown
- Table
- Pagination
- Tabs
- Card
- Toast
- ConfirmDialog
- RowActions

Reuse these before creating page-specific duplicates.

---

# Forms

Shared form primitives belong under:

    resources/js/components/forms/

Examples:

- FormField
- FormError
- SearchInput
- FilterBar

All validation errors returned by Laravel should be presented clearly in the UI.

Do not silently swallow 422 responses.

---

# API Layer

Frontend HTTP logic belongs under:

    resources/js/api/

Examples:

    api/client.js
    api/auth.js
    api/clients.js
    api/tasks.js
    api/users.js
    api/documents.js
    api/reports.js
    api/seo.js
    api/workspaces.js

Do not scatter raw `fetch()` calls throughout page components when the operation belongs in an API module.

Use the shared API client for:

- base URL handling
- workspace-aware URLs
- authentication
- CSRF where appropriate
- 401
- 403
- 404
- 422
- 500 handling

---

# Row Actions Standard

All record/table row actions should use the shared three-dot menu.

Example:

    ⋮

Menu:

    View
    Edit
    Manage
    ─────────
    Delete

Do not display large groups of inline row buttons.

Primary page actions remain visible:

    + Add User
    + Add Client
    + Add Workspace
    + Create Task

Rule:

    Page-level primary action
        → visible button

    Record-level actions
        → three-dot dropdown

Destructive actions must:

- appear last
- use danger styling
- require confirmation
- respect backend authorization

---

# Delete vs Remove

These are different operations.

Use:

    Delete

when deleting the entity.

Use:

    Remove from Workspace

when removing only an association.

Do not delete a platform User when the intended action is merely removing that user from one workspace.

For important historical/business data, inspect whether:

- Soft Delete
- Archive
- Deactivate
- Remove association

is safer than hard delete.

Never blindly cascade-delete business history.

Current rules:

- Remove from Workspace clears `users.workspace_id` and client access; the account and owned records stay.
- Delete User is Super Admin only; tasks/documents keep their rows with a null owner; users with time entries cannot be deleted.
- Clients with tasks or documents are archived (`status = archived`), not deleted.
- Tasks with time entries cannot be deleted.
- Workspaces are deactivated. Permanent deletion is only allowed for an inactive workspace with no clients, tasks or documents, and activity history is kept.
- A workspace must always keep at least one active admin.
- Disconnecting an integration clears its credentials and keeps the record; Delete removes it.
- Content pillars used by articles or calendar entries cannot be deleted; deactivate them instead.
- Deleting an article removes its score history; a linked calendar entry is kept without its article.

---

# Design System

The portal should visually match the Bough Digital brand.

General design:

- clean white backgrounds
- dark navy text
- subtle neutral borders
- Bough green/lime accents
- professional spacing
- rounded but not excessively rounded controls
- subtle shadows
- clean cards
- strong typography
- proper icons

Avoid:

- generic Bootstrap-looking UI
- excessive gradients
- oversized controls
- excessive pill shapes
- random colors
- emoji used as application icons

Use the existing icon library.

---

# Workspace Branding

Each workspace supports branding such as:

- workspace name
- logo
- primary colour
- accent colour

Workspace logo must be an uploaded file.

Do not restore a manual `Logo URL` field unless explicitly requested.

Database should store a storage path such as:

    workspaces/39/logo/logo.jpg

not a hardcoded browser URL.

Frontend should receive/use a resolved URL such as:

    logo_url

React must not assume that `logo_path` is browser-accessible.

---

# File Storage Architecture

Use Laravel Filesystem.

Do not use raw filesystem operations when Laravel Storage provides the required functionality.

Code should remain portable between:

    local/public filesystem

and:

    AWS S3

Do not hardcode:

    /storage/
    storage/app/
    public/storage/
    public_html/storage/
    portal.local

into business logic.

Use:

    Storage::disk(...)

and Laravel filesystem URL generation.

---

# Workspace Storage Isolation

Storage must be isolated by workspace.

Preferred physical storage namespace uses an immutable identifier:

    workspaces/{workspace-id}/

Example:

    workspaces/39/
    ├── logo/
    ├── documents/
    ├── reports/
    ├── uploads/
    └── clients/

The user-facing workspace slug remains suitable for URLs:

    /bough/dashboard

but should not be required as the permanent physical storage identity.

Reason:

Workspace slug may change.

Workspace ID does not.

Do not create separate Laravel application folders per workspace.

Implementation notes:

- Logos: `workspaces/{id}/logo/logo-<random>.<ext>` with `workspaces.logo_disk`; a unique filename per upload means a replacement never overwrites the current logo.
- Client documents: `workspaces/{id}/clients/{client_id}/documents/<uuid>.<ext>` with `documents.storage_disk` / `storage_path`, on the private disk.
- Private documents are served only through `GET /{workspace}/api/documents/{id}/download` (authorized; streams locally, redirects to a short-lived signed URL on S3). React uses `download_url`, never a storage path.
- `php artisan workspaces:migrate-storage` moves files from the retired slug-based layout.

---

# Client Storage

Future client storage should follow the same isolation principle.

Example:

    workspaces/39/
    └── clients/
        └── 108/
            ├── documents/
            ├── reports/
            └── uploads/

Prefer immutable IDs over client names/slugs for physical storage.

---

# Public vs Private Storage

Workspace branding assets such as logos may be publicly accessible.

Business data should normally remain private.

Examples of private data:

- client documents
- reports
- internal uploads
- generated deliverables
- sensitive exports

Do not make the entire workspace S3 prefix public merely because logos need browser access.

Use appropriate signed/temporary URLs for private files when S3 is implemented.

---

# Logo Upload Safety

When replacing a logo:

    upload new logo
        ↓
    verify upload
        ↓
    update database
        ↓
    delete old logo

Do not delete the existing working logo before the replacement is successfully stored and recorded.

If database persistence fails after uploading a new file, clean up the orphaned new file where practical.

---

# Workspace Settings

Workspace preferences are stored in:

    workspaces.settings

PostgreSQL JSON/JSONB.

Current examples include:

    timezone
    date_format
    datetime_format

Normal users must NOT edit raw JSON.

Expose structured controls in the UI.

When updating known settings, preserve unknown/future JSON keys.

Do not replace the entire settings object unnecessarily.

---

# Date and Time

Current resolution hierarchy:

    User override
        ↓
    Workspace preference
        ↓
    Application default

Application defaults:

    timezone: Australia/Sydney
    date format: d/m/Y
    datetime format: d/m/Y H:i

Timestamps remain stored in UTC where appropriate.

Display conversion happens according to resolved preferences.

---

# Date Rendering

Never directly display raw database timestamps such as:

    2026-08-19T03:35:46.000000Z

Use the shared frontend date utilities.

For example:

    formatDate(...)
    formatDateTime(...)

Do not create page-specific date formatting implementations.

---

# Workspace Business Time

There is a distinction between:

    User display timezone

and:

    Workspace business timezone

User-facing timestamps may use the user's override.

Workspace business operations such as:

- scheduled reports
- monthly automation
- campaign windows
- scheduled integrations

should generally use the Workspace timezone.

Do not accidentally schedule workspace jobs according to whichever user happens to create them.

---

# MCP Architecture

MCP is a first-class interface into the same Bough Digital system.

Current MCP endpoint pattern:

    /mcp/{workspace}

Existing/initial MCP tools include:

- list_clients
- search_clients
- get_client
- create_client
- update_client
- list_client_documents
- link_google_doc
- list_tasks

Future MCP tools should remain:

- small
- clearly named
- strongly validated
- permission-aware
- workspace-aware

MCP tools should delegate to shared services rather than duplicating controller logic.

---

# MCP Security

An MCP request must never gain unrestricted database access merely because it comes from Claude or another MCP client.

Every tool must enforce:

- authentication
- workspace resolution
- authorization
- input validation

MCP is another application interface, not a security bypass.

---

# REST API

REST API routes follow the workspace boundary:

    /{workspace}/api/...

These routes are the portal API: `web` + `auth` (session and CSRF). They are not a token-authenticated external API. A future external REST API must use its own versioned prefix with `auth:sanctum` and without the `web` group.

Keep API responses consistent.

Use resources/transformers where appropriate.

Do not leak:

- credentials
- internal filesystem paths unnecessarily
- secrets
- tokens
- unrelated workspace data

---

# Services

Business operations shared by Web/API/MCP belong in services.

Examples:

    ClientService
    DocumentService
    WorkspaceService
    WorkspaceStorageService
    TaskService

Avoid fat controllers.

Controllers should generally:

    validate
    authorize
    delegate
    return response

Business rules belong in services/models/domain logic as appropriate.

---

# Activity Logging

Important mutations should be logged where the current activity system supports it.

Examples:

    workspace.created
    workspace.updated
    workspace.logo_uploaded
    workspace.logo_replaced
    workspace.logo_removed
    workspace.slug_changed

    client.created
    client.updated
    client.deleted

    document.created
    document.updated
    document.deleted

Do not log:

- passwords
- API secrets
- access tokens
- raw private document contents

---

# External Integrations

Planned integrations include:

- AWS S3
- Google APIs
- Google Docs
- Semrush
- HubSpot
- Screaming Frog workflows

Integration-specific API code should not be mixed directly into React components or large controllers.

Prefer dedicated services, for example:

    Services/Google/
    Services/Semrush/
    Services/HubSpot/
    Services/Storage/

Credentials must come from secure configuration/environment variables.

Never commit credentials.

Client integrations (`client_integrations`, Client → Integrations tab):

- `App\Enums\IntegrationProvider` is the provider catalogue (label, auth method, whether it can be set up yet). OAuth providers are listed but not available until their consent flow is built; no provider API is called yet.
- Per-client credentials are stored in `credentials` with the `encrypted:array` cast (APP_KEY) and are hidden from serialization. Read them only in backend provider code; never return them in resources, React props or activity details. Resources expose `credential_configured` only.
- `ClientIntegrationService` owns create/update/disconnect/delete. One integration per provider per client (unique index).
- Viewing status requires client access; managing integrations is workspace Admin only.

---

# Client Content

Content (Client → Content) is separate from Documents. Documents are files and links; Content is editorial work:

- `content_pillars` — a client's main topics.
- `content_calendar_entries` — planned content ("what are we going to produce?"). `article_id` links an entry to the article written for it (one article per entry).
- `articles` — the content actually produced, with body, SEO fields and tags. Word count is derived from the body.
- `article_scores` — AI or human scores with a category breakdown; an article keeps its score history. No AI scoring engine exists yet.

`ContentPillarService`, `ContentCalendarService` and `ArticleService` (`App\Services\Content`) hold the business rules for Portal, API and future MCP tools. Related IDs (pillar, writer, article, calendar entry) are always checked against the owning client. Writers must be able to access the client (`User::assignableToClient`).

Access: anyone with client access can view; Admins and Team Leaders manage; a writer may edit articles assigned to them; only Admins delete.

# Google Documents

A linked Google Doc should normally be represented as a document record associated with the correct:

    workspace
    client
    folder/category
    document type

The system may store the Google Doc URL/identifier, but linking a document must not imply unrestricted access to the user's entire Google Drive.

---

# Background Automation

Scheduled/background work should use Laravel queues/workers where appropriate.

Examples:

- scheduled emails
- inbox monitoring
- Semrush data pulls
- notifications
- report generation
- integration synchronization

Do not perform long external API jobs synchronously inside normal HTTP requests when they belong in a queue.

Jobs must retain workspace context explicitly.

---

# Error Handling

Do not hide failures.

Frontend should distinguish at least:

    401 — unauthenticated
    403 — unauthorized
    404 — missing
    422 — validation
    500 — server failure

Present useful messages without exposing stack traces or secrets.

---

# PostgreSQL

PostgreSQL is canonical.

Use Laravel/PostgreSQL-compatible schema features.

JSON preferences may use JSONB where appropriate.

Do not convert PostgreSQL schema to MySQL simply to make local tooling easier.

Do not introduce database-specific raw SQL unless there is a clear reason.

---

# Migrations

Migrations must be incremental and safe for existing data.

Before changing a column:

1. inspect current schema
2. inspect existing data
3. inspect code references
4. create safe migration
5. run normal `php artisan migrate`
6. verify existing records

Never solve a migration mismatch by wiping the database.

---

# Existing Data Is Important

Development records may have been manually created/restored.

Do not:

- replace users with seed data
- reset workspaces
- reset clients
- overwrite manually entered data
- assume seeders represent the current desired database state

Seeders are not authorization to destroy existing data.

---

# Authentication

The application uses Laravel session authentication for the portal and portal API. Sanctum bearer tokens are used for MCP.

Deactivated users are signed out on their next request (`EnsureUserIsActive`), and a password change signs out other sessions (`AuthenticateSession`). Non-platform users without an active workspace cannot sign in.

Current flows include:

- Login
- Logout
- Remember Me
- Forgot Password
- Reset Password
- Profile

Unauthenticated workspace/admin routes must redirect appropriately to login.

The root:

    /

should not accidentally expose an authenticated admin shell to logged-out users.

---

# Root Routing

Expected conceptual behavior:

    /
        ↓
    unauthenticated → /login
    authenticated Super Admin → /admin/dashboard
    authenticated workspace user → appropriate workspace dashboard

Follow the actual role/workspace resolution logic already implemented.

---

# Slugs

Workspace slugs are user-facing route identifiers.

Generate safe slugs with Laravel helpers such as:

    Str::slug()

Validate uniqueness.

Do not allow:

    ../
    /
    \
    unsafe filesystem/path characters

Do not trust slugs as authorization boundaries.

---

# UI Loading States

Mutating actions must prevent double submission.

Examples:

- Save
- Delete
- Remove
- Upload
- Replace Logo
- Create
- Update

Provide loading states and disable repeated submissions while a request is in progress.

---

# Toasts / Feedback

Successful mutations should provide clear feedback.

Examples:

    Workspace updated successfully.
    Client created successfully.
    User removed from workspace.
    Logo uploaded successfully.

Failures should show useful error feedback.

Do not silently fail.

---

# Responsive Design

All portal screens must work at:

- desktop
- tablet
- mobile

Desktop tables may use multiple columns.

Mobile layouts should avoid horizontal action-button clutter.

Use the shared three-dot row action menu.

---

# Accessibility

Interactive elements must use semantic controls.

Provide:

- keyboard access
- focus states
- aria-label where needed
- Escape handling for menus/modals
- accessible form labels

Do not make clickable `<div>` elements when a `<button>` is appropriate.

---

# Before Modifying Existing Code

Always inspect the relevant existing implementation first.

Do not assume a feature is missing.

Before implementing:

1. inspect routes
2. inspect controller
3. inspect model
4. inspect policy
5. inspect service
6. inspect React page/component
7. inspect API module
8. inspect migrations/schema
9. inspect relevant tests

Extend existing architecture rather than creating parallel implementations.

---

# Do Not Rewrite Unrelated Code

Keep changes scoped to the requested task.

Do not:

- redesign unrelated pages
- rename unrelated routes
- replace working authentication
- change database engines
- modify MCP behavior unnecessarily
- modify storage architecture unnecessarily
- install major packages without need

Small, coherent changes are preferred.

---

# Verification

For normal changes, use safe verification such as:

    php artisan optimize:clear
    php artisan migrate:status
    vendor/bin/pint --test
    npm run build
    php artisan route:list

Run:

    php artisan migrate

only when a migration is required.

Run automated tests only after confirming they use an isolated testing database.

---

# Runtime Verification

Compilation alone is not sufficient.

When practical, verify the actual changed workflow.

For example, a Workspace logo fix should verify:

    upload
    database path
    filesystem existence
    generated URL
    HTTP response
    React rendering

A CRUD feature should verify:

    create
    view
    edit
    update
    delete/remove/deactivate as appropriate
    authorization
    workspace isolation

Do not report a feature as complete merely because `npm run build` passes.

---

# Final Reports

After completing a task, provide a concise report containing:

    Root cause / requirement
    Files changed
    Architecture used
    Database changes
    Authorization behavior
    Runtime verification
    Build status
    Pint status
    Test status

Use:

    PASS
    FAIL
    NOT RUN

Do not claim PASS unless actually verified.

If something could not be verified, say so explicitly.

---

# Non-Negotiable Rules

1. Never wipe the `boughdigital` PostgreSQL development database.
2. Never run destructive tests against the development database.
3. Never weaken workspace isolation.
4. Never rely on frontend permission checks for security.
5. Never expose MCP as unrestricted database access.
6. Never hardcode local storage URLs when Laravel Filesystem can resolve them.
7. Never make private client files public to solve a logo/storage issue.
8. Never put the React application back into one giant `app.jsx`.
9. Never duplicate core business logic separately for Web/API/MCP.
10. Never report successful runtime verification that was not actually performed.
11. Preserve existing data unless the user explicitly requests its deletion.
12. Keep new features compatible with the planned AWS S3 production architecture.