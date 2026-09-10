<laravel-boost-guidelines>
=== .ai/taskly rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
