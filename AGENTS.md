# Project Conventions (owner's house style)

These rules come from the project owner and take precedence over the generic guidelines below.
The folders described here are pre-approved; creating them does not need extra approval.

## Database safety: never wipe the database

- AI agents must **never** run `php artisan migrate:fresh`. The same applies to the other commands that wipe or roll back data: `migrate:refresh`, `migrate:reset`, `migrate:rollback` and `db:wipe`.
- Use `php artisan migrate` to apply new migrations. If a migration fails or the schema looks wrong, stop and tell the owner instead of resetting the database.
- Tests get a clean database through `RefreshDatabase` on the test connection. That is the only place a database reset is allowed.

## Always prioritise security and optimisation

Treat security and performance as part of every change, not a later clean-up. If a quick solution
and a secure or efficient one differ, choose the secure and efficient one and mention the trade-off.

### Security

- **Authorise every action.** Each route sits in the correct role middleware group. Every controller action that touches a record checks ownership through a Policy (`$this->authorize()` / `Gate`). Never trust an ID from the request.
- **Validate every input** in a FormRequest (see below), including query strings and route-bound values. Whitelist allowed values (`Rule::in`, enums) instead of blacklisting.
- **Mass assignment:** set `$fillable` on every model. Never pass `$request->all()` to `create()` or `update()`; use `$request->validated()`.
- **No raw SQL with user input.** Use Eloquent or the query builder with bindings. `DB::raw` / `whereRaw` only with bound parameters.
- **Uploads:** validate MIME type and size, generate the stored filename yourself (never use the client's name as the path), keep private files off the public disk, and serve them through an authorised controller or temporary signed URL.
- **Secrets:** keys only in `.env` and read via `config()`. Never expose them to the frontend (only `VITE_`-prefixed values reach the browser, so never prefix a secret with `VITE_`), never log them, never commit `.env`.
- **Output:** never use `dangerouslySetInnerHTML` with user or AI content unless it is sanitised first.
- **Rate-limit** login, public API and any endpoint that triggers paid AI work or heavy processing.
- **Signed internal calls:** service-to-service callbacks must verify the HMAC signature and timestamp before doing anything.
- **Data in responses:** return only the fields the page needs (API Resources or `select()`), never whole models with hidden or internal fields.

### Optimisation

- **No N+1 queries:** eager-load relations (`with()`, `withCount()`), and select only the columns you need.
- **Index** every foreign key and every column used in `where`, `orderBy` or joins, in the same migration that adds it.
- **Paginate** every list. Never `->get()` an unbounded table for display. Use `chunk()` / `lazy()` for batch processing.
- **Queue slow work** (file processing, external API calls, emails, exports) instead of doing it in the request.
- **Cache** expensive or repeated results (config lookups, AI responses, aggregates) and invalidate them on change.
- **Frontend:** use TanStack Query caching (sensible `staleTime`, invalidate only the affected keys), lazy-load heavy pages and components, virtualise long lists, debounce search and autosave inputs, and avoid unnecessary re-renders.
- **Don't add a dependency** for something a few lines of code or an existing package already does.

## Organise by role, with `all` for shared code

Every user role gets its own route file and its own folder in controllers, requests, pages and
components. Anything used by more than one role goes in an `all` folder (or `all.php` for routes).

```
routes/
├── web.php              # bootstraps only: requires the role route files below
├── all.php              # routes shared by every authenticated role
├── admin.php            # one file per role (admin.php, user.php, ...)
└── ...
app/Http/
├── Controllers/
│   ├── All/             # controllers shared across roles
│   ├── Admin/           # one folder per role
│   └── ...
└── Requests/
    ├── All/             # shared FormRequests
    ├── Admin/           # one folder per role, mirroring Controllers/
    └── ...
resources/js/
├── pages/
│   ├── all/             # pages shared across roles
│   ├── admin/           # one folder per role
│   └── ...
├── components/
│   ├── ui/              # shadcn/ui primitives (starter kit, leave as is)
│   ├── all/             # shared feature components
│   ├── admin/           # one folder per role
│   └── ...
├── hooks/               # TanStack Query hooks that wrap the axios calls
└── lib/
    ├── utils.ts         # starter kit `cn()` helper, leave as is
    └── utilities.ts     # common helper functions go here
```

- **Roles in this project:** `admin`, `encoder`, `user` (public viewer). Route files: `routes/admin.php`, `routes/encoder.php`, `routes/user.php`, `routes/all.php`. Admins pass every role check: they reach encoder features through the encoder route group's `role:encoder,admin` middleware, so encoder pages are not duplicated under `admin/`. See `PROJECT_SPEC.md` §1 "Roles".
- Each role route file wraps its routes in that role's middleware group. `web.php` loads them with `require __DIR__.'/admin.php';` etc.
- PHP folders and namespaces are PascalCase (`Controllers/Admin`, `App\Http\Controllers\Admin`). JS folders are lowercase (`pages/admin`).
- When adding something new, decide first: is it used by one role (put it in that role's folder) or by several (put it in `all`)?

## One component per file: sub-components go in `partials/`

- A `.tsx` file holds exactly one component. Never define a second full component in the same file.
- When a page or component needs sub-components, put each one in its own file in a `partials/` folder next to the parent, and import it.

```
pages/admin/
├── project-workspace.tsx        # the page component only
└── partials/
    ├── transcript-list.tsx      # one sub-component per file
    └── metadata-form.tsx
components/all/
├── video-player.tsx
└── partials/
    └── player-controls.tsx
```

- Tiny inline JSX inside a component (a `.map()` row, a conditional block) is fine. Once it has its own props, state or handlers, move it to `partials/`.

## Validation: always a FormRequest

- All input validation lives in FormRequest classes under `app/Http/Requests/{Role}/` (or `All/`). Create them with `php artisan make:request Admin/StoreSomethingRequest`.
- Controllers type-hint the FormRequest and use `$request->validated()`. No inline `$request->validate()` in controllers.

## Frontend data fetching: TanStack Query + axios in hooks

- Server calls from React go through axios, wrapped in TanStack Query hooks (`useQuery` / `useMutation`) in `resources/js/hooks/`. Components and pages call the hooks, never axios directly.
- Name hooks after what they do, e.g. `useProjects`, `useUpdateSegment`.
- Build request URLs with Wayfinder (`@/actions/...` / `@/routes/...`, e.g. `store.url()`) and pass them to axios. Do not hardcode URL strings.
- After a mutation, invalidate the related query keys so the UI refreshes.
- Inertia v3 no longer ships axios, so `axios` and `@tanstack/react-query` must be installed explicitly (see `package.json`).

## Shared helpers

- Reusable, non-React helper functions (formatting, parsing, time/duration helpers, etc.) go in `resources/js/lib/utilities.ts`. Check it before writing a new helper.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

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

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

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

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
