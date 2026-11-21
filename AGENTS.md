# Project Conventions (owner's house style)

These rules come from the owner and override the generic guidelines below. The folders listed here are pre-approved.

## Database safety

- **Never** run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` or `db:wipe`. Apply migrations with `php artisan migrate`; if one fails or the schema looks wrong, stop and tell the owner.
- The only allowed reset is `RefreshDatabase` in tests.

## Security and optimisation come first

Part of every change, not a later clean-up. If the quick fix and the secure/efficient one differ, pick the latter and mention the trade-off.

**Security**

- Every route is in the right role middleware group. Every action touching a record checks ownership via a Policy (`$this->authorize()` / `Gate`). Never trust an ID from the request.
- Validate every input (query strings and route values too) in a FormRequest; whitelist with `Rule::in` / enums.
- `$fillable` on every model. Use `$request->validated()`, never `$request->all()`.
- No raw SQL with user input; `DB::raw` / `whereRaw` only with bindings.
- Uploads: validate MIME and size, generate the stored filename, keep private files off the public disk, serve them through an authorised controller or a temporary signed URL.
- Secrets live in `.env`, read via `config()`. Never log them, never commit `.env`, never prefix them with `VITE_` (those reach the browser).
- No `dangerouslySetInnerHTML` with user or AI content unless sanitised.
- Rate-limit login, the public API and anything that triggers paid AI work or heavy processing.
- Service-to-service callbacks verify the HMAC signature and timestamp first.
- Responses carry only the fields the page needs (API Resources / `select()`), never whole models.

**Optimisation**

- No N+1: eager-load (`with()`, `withCount()`), select only needed columns.
- Index every foreign key and every `where` / `orderBy` / join column, in the same migration.
- Paginate every list; `chunk()` / `lazy()` for batches. Queue slow work (files, external APIs, email, exports).
- Cache expensive or repeated results and invalidate them on change.
- Frontend: TanStack Query with sensible `staleTime`, invalidate only affected keys; lazy-load heavy pages, virtualise long lists, debounce search and autosave, avoid needless re-renders.
- Don't add a dependency for what a few lines or an installed package already does.

## Organise by role, `all` for shared code

- Roles: `admin`, `encoder`, `user` (public viewer). Admins pass every role check and reach encoder features through `role:encoder,admin`, so encoder pages are not duplicated under `admin/` (see `PROJECT_SPEC.md` §1).
- Routes: `web.php` only `require`s `all.php`, `admin.php`, `encoder.php`, `user.php`; each wraps its routes in that role's middleware group.
- One folder per role (plus `All`/`all` for anything shared by several roles) in `app/Http/Controllers/`, `app/Http/Requests/` (mirrors Controllers), `resources/js/pages/` and `resources/js/components/`. PHP folders are PascalCase (`Controllers/Admin`), JS folders lowercase (`pages/admin`).
- Leave `components/ui/` (shadcn) and `lib/utils.ts` (`cn()`) as shipped.
- Decide first: used by one role → that role's folder; by several → `all`.

## One component per file

- A `.tsx` file holds one component. Sub-components go in their own files in a `partials/` folder next to the parent.
- Tiny inline JSX (a `.map()` row, a conditional) is fine; once it has its own props, state or handlers, move it to `partials/`.

## One folder per page

- Every Inertia page lives in its own `{name}-page/` folder, together with its own `partials/`. Never put a page file loose in a role folder, and never share one `partials/` folder between several pages.

    ```
    pages/public/welcome-page/welcome.tsx
    pages/public/welcome-page/partials/welcome-hero.tsx
    pages/admin/system-status-page/system-status.tsx
    pages/admin/system-status-page/partials/horizon-card.tsx
    pages/encoder/records/show-page/show.tsx
    ```

- `pages/public/` holds guest pages (no login, e.g. the landing page); they render without `AppLayout`.
- The Inertia component name is the full path: `Inertia::render('admin/system-status-page/system-status')`, and tests assert the same name.
- Page paths in `PROJECT_SPEC.md` (e.g. `pages/encoder/records/show.tsx`) name the page; the `{name}-page/` folder is implied.
- A partial used by several pages of one role moves to `components/{role}/`; by several roles, to `components/all/`. Don't import one page's partials from another page.

## Validation: always a FormRequest

- FormRequests live in `app/Http/Requests/{Role}/` (or `All/`), created with `php artisan make:request Admin/StoreSomethingRequest`. Controllers type-hint them and use `$request->validated()`; no inline `$request->validate()`.

## Frontend data fetching

- React calls the server only through TanStack Query hooks (`useQuery` / `useMutation`) in `resources/js/hooks/`, named for what they do (`useProjects`, `useUpdateSegment`). Components never call axios directly.
- Hooks use the shared axios instance and Wayfinder URLs (`@/actions/...`, `@/routes/...`, e.g. `store.url()`); never hardcode URLs. Invalidate related query keys after a mutation.
- Inertia v3 doesn't ship axios: `axios` and `@tanstack/react-query` are explicit dependencies.

## Shared helpers

- Reusable non-React helpers (formatting, parsing, time) go in `resources/js/lib/utilities.ts`. Check it before writing a new one.

## Local setup

- Laravel runs on the host with `composer run dev`; Docker runs Redis and the Python AI service (`docker compose up -d`). Details in `README.md`, reasons in `DECISIONS.md`.

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
