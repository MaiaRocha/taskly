# Taskly Project Guidelines

## Sources of Truth

Before planning or implementing substantial work, read:

- `docs/DECISIONS.md`
- `docs/SPEC.md`
- `docs/UI-UX.md`

These documents contain the approved architecture, functional scope, domain rules, implementation phases, and UI/UX direction for Taskly.

When a request conflicts with these documents, do not silently choose one interpretation.

Identify the conflict and ask for approval before changing architecture, stack, authentication, persistence strategy, security model, or major dependencies.

---

## Architecture

Taskly is a modular monolith.

The approved application boundary is:

```text
Vue 3 SPA
    ↓ HTTP / JSON
Laravel REST API
    ↓
Eloquent
    ↓
MariaDB
```

Frontend and backend live in the same repository and initially share the same deployment.

The REST boundary is intentional.

Do not replace it with Inertia or server-rendered application flows.

---

## Approved Backend Stack

Use:

- PHP 8.3+
- Laravel 13
- MariaDB
- Eloquent ORM
- Laravel Fortify
- Laravel Sanctum
- Form Requests
- Policies
- API Resources
- PHP Enums
- Laravel Filesystem
- Pest
- Laravel Pint
- Laravel Boost

Prefer first-party Laravel features and established Laravel conventions.

Avoid unnecessary abstractions.

---

## Approved Frontend Stack

Use:

- Vue 3
- TypeScript
- Vue Router
- Pinia
- Axios
- Vite
- Tailwind CSS
- Lucide Icons

`shadcn-vue` may only be introduced when justified and must be visually customized for Taskly.

Do not use Inertia.js.

Keep Axios configuration centralized.

Use Pinia only for meaningful shared application state.

---

## REST API

Taskly uses an explicit REST API consumed by its first-party Vue SPA.

Use Eloquent API Resources for response contracts.

Use appropriate HTTP semantics and status codes.

Do not expose Eloquent Models directly as public API contracts when a Resource is appropriate.

Do not introduce API versioning such as `/api/v1` unless explicitly approved.

The approved initial routes in `docs/DECISIONS.md` and `docs/SPEC.md` are intentionally unversioned.

---

## Authentication

The first-party SPA must use Laravel Sanctum stateful SPA authentication with Laravel Fortify.

Expected flow:

```text
GET /sanctum/csrf-cookie
POST /login
Laravel session established
HttpOnly session cookie
Authenticated REST API requests
```

Do not implement custom JWT authentication.

Do not store authentication tokens in `localStorage` or `sessionStorage`.

Do not introduce Passport or OAuth for Taskly login.

Authentication and authorization are separate concerns.

---

## Authorization and Ownership

Every user may access only their own data.

Protect horizontal access with Laravel Policies.

Never trust client-provided ownership information.

The frontend must not be authoritative for:

- `user_id`
- project ownership
- task ownership
- tag ownership
- attachment ownership
- `completed_at`
- persisted ordering rules

Authorization must be enforced on the backend and covered by tests involving multiple users.

---

## Controllers and Application Logic

Keep controllers focused on HTTP orchestration.

Use Form Requests for validation.

Use Policies for authorization.

Use API Resources for JSON transformation.

Use dedicated Actions only when they encapsulate meaningful business operations.

Examples that may justify Actions include:

- moving a task
- storing attachments
- deleting a task with associated files
- deleting a project with associated files

Do not create Service or Action layers merely for architectural symmetry.

Do not introduce Repository Pattern.

---

## Domain

Core entities are:

- User
- Project
- Task
- Tag
- Attachment

Follow the field definitions and relationships documented in `docs/SPEC.md`.

Do not alter the domain model substantially without approval.

---

## Task Status

Task status must use a PHP Enum with persisted values:

```text
not_started
in_progress
completed
cancelled
```

When entering `completed`, set `completed_at` when it is not already set.

When leaving `completed`, clear `completed_at`.

A cancelled task is not completed.

Do not allow the client to control `completed_at` directly.

---

## Overdue Tasks

Do not persist an `overdue` column.

Overdue is derived when:

```text
due_at is not null
AND due_at < now
AND status is not completed
AND status is not cancelled
```

Store dates consistently and expose them to the frontend in an appropriate API representation.

The browser is responsible for displaying dates in the user's local timezone.

---

## Tags

Tags belong to users.

A task may have at most five tags.

Tag names have a maximum length of 30 characters.

Logical duplicate tag names must be prevented per user through `normalized_name`.

Do not allow a user to associate another user's tags with a task.

---

## Attachments

Attachments are private.

Do not expose private attachments through predictable public filesystem URLs.

Access, preview, download, and deletion must respect authorization.

Initial limits are:

```text
5 MB per file
10 attachments per task
5 files per upload request
```

Validate real MIME types in addition to extension.

Supported formats and cleanup behavior are defined in `docs/SPEC.md`.

Deletion of tasks or projects must not leave related physical files orphaned when they can be safely removed.

---

## Ordering and Kanban

Projects and tasks use integer `position` values.

Use simple reindexing for the MVP.

Do not introduce fractional indexing without approval.

Moving a task in Kanban must persist both:

- status
- position

Prefer the simplest reliable implementation.

---

## Database

MariaDB is the approved database.

Use foreign keys, indexes, constraints, and data types that reflect actual query and domain requirements.

Do not switch to PostgreSQL, SQLite as the main development database, or another database without approval.

Tests may use an appropriate isolated testing strategy, but behavior relevant to MariaDB must remain compatible.

Avoid N+1 queries and unnecessary data loading.

---

## UI/UX Direction

Before substantial UI implementation, read `docs/UI-UX.md`.

The visual direction is:

```text
modern
productive
organized
colorful in a controlled way
light mode
high quality on desktop
well adapted to mobile
```

ClickUp is a UX and organization reference only.

Do not clone ClickUp.

Taskly must have its own identity.

Approved interaction patterns include:

- fixed desktop sidebar
- mobile navigation drawer
- List and Kanban views
- rich Kanban task cards
- right-side Task Drawer for create/edit
- Lucide icons
- clear loading, empty, error, validation, and success states

Before implementing the main UI, present the proposed structure and any new dependencies for review.

---

## Responsive Behavior

Desktop is the primary workspace, but mobile must remain genuinely functional.

Do not solve responsive problems by removing mandatory functionality.

Validate navigation, forms, List View, Kanban, Task Drawer, filters, and attachments at mobile sizes.

---

## Testing

Use Pest.

Prioritize Feature Tests for application behavior.

Security-sensitive and business-critical behavior must have tests.

Important coverage includes:

- authentication
- cross-user authorization
- Project CRUD
- Task CRUD
- task status transitions
- `completed_at`
- Kanban persistence
- tags
- attachment security and limits
- REST response contracts and status codes

Use the narrowest relevant test set while implementing, then validate the broader suite at appropriate checkpoints.

---

## Dependencies and Architecture Changes

Do not introduce these without explicit approval:

- Inertia
- Repository Pattern
- microservices
- CQRS
- Event Sourcing
- Redis
- Horizon
- Reverb
- WebSockets
- Passport
- custom JWT
- Octane
- Scout
- external queues
- paid external services
- major new frontend frameworks
- major structural libraries

Do not add dependencies merely to demonstrate more technologies.

Each dependency must solve a concrete problem.

---

## Optional Technologies

Laravel Telescope is development-only and optional.

Laravel Precognition is optional and should only be evaluated after the core authentication, domain, validation, and frontend flows are stable.

Do not implement optional technologies at the expense of mandatory functionality.

---

## Scope Priority

When time or complexity requires prioritization, use:

```text
1. Mandatory functionality
2. Security
3. Stability
4. Essential tests
5. Frontend quality and UX
6. Product enhancements
7. Optional differentiators
```

Do not sacrifice core requirements to add impressive but unnecessary technology.

---

## Planning Before Implementation

Before a substantial implementation phase:

1. inspect the current repository state
2. read the relevant project documentation
3. activate relevant Claude skills
4. use Laravel Boost tools and version-specific documentation when applicable
5. identify ambiguities, risks, and dependencies
6. propose a focused implementation plan
7. wait for approval when the request requires a planning checkpoint

Do not silently expand scope.

Do not make architecture decisions merely because they are convenient to generate.

---

## AI-Assisted Development

Claude Code is the primary implementation agent for this project.

AI-generated code must be critically reviewed.

Before considering generated work complete, verify:

- scope adherence
- Laravel version compatibility
- security
- authorization
- validation
- REST semantics
- database behavior
- frontend behavior
- tests
- performance
- maintainability

Compiling or passing a superficial test is not enough by itself.

Use Laravel Boost to ground Laravel-specific decisions in the real project and installed package versions.

---

## Documentation and Traceability

Do not automatically create additional documentation files.

Documentation changes require an explicit request.

AI-use documentation should be curated and truthful.

Record meaningful prompts, technical decisions, reviews, and corrections rather than dumping complete AI conversations.

Never fabricate an AI interaction, correction, or decision that did not occur.

---

## Git

Changes should be reviewable and logically scoped.

Before proposing a commit:

- inspect the diff
- run relevant tests
- run required formatters
- verify that unrelated files were not modified

Do not make commits unless explicitly requested or authorized for the current workflow.

---

## Final Principle

Prefer:

```text
simple
secure
Laravel-native
tested
maintainable
intentional
```

over:

```text
complex
clever
technology-heavy
prematurely abstracted
```

For substantial work, follow:

```text
inspect
→ analyze
→ propose
→ validate
→ implement
→ test
→ review
```
