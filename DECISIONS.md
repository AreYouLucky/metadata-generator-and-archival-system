# Decisions

Choices made while building, and any deviation from [PROJECT_SPEC.md](PROJECT_SPEC.md). Newest
phase first.

## Phase 1 — Core app (decided before starting)

- **Mixed AI providers (owner's choice).** Transcription runs **locally** with `faster-whisper` on
  the dev laptop's NVIDIA RTX 3050 (6 GB). OpenAI handles metadata, translation, frame descriptions
  and embeddings. Reasons:
  - Transcription is about 85–90% of the OpenAI bill: roughly $0.27–0.36 per video hour, against
    $0.01–0.03 for metadata.
  - Local transcription keeps the audio of possibly restricted recordings on the machine.
  - The small models that fit in 6 GB are clearly weaker at metadata (structured JSON, Filipino and
    Taglish).
- **Provider changes from the spec:**
  - `TRANSCRIPTION_PROVIDER` defaults to `local` (the spec had `openai`), and the local provider
    moves from Phase 6 to Phase 1. Defaults are `large-v3`, `cuda`, `int8_float16`, with `medium`
    or CPU `int8` as fallbacks.
  - `LLM_PROVIDER` defaults to `openai` (the spec had `anthropic`).
  - Every provider stays swappable behind the same interface. The OpenAI transcription provider is
    still built, so it can be switched on per environment.
- **Consequences:**
  - The AI worker needs GPU access. Use `docker-compose.gpu.yml`; it needs a current NVIDIA driver
    and Docker Desktop's WSL2 GPU support.
  - Production needs a GPU server, or switches transcription to `openai`.
  - The CUDA-enabled image is much larger, so the first build will be slow on the dev connection.
  - OpenAI model IDs are set from OpenAI's current docs at the start of Phase 1, not from memory.
  - `OPENAI_API_KEY` lives only in `ai-service/.env`.

## Phase 0 — Scaffold

### Infrastructure

- **The app uses the PostgreSQL installed on the host (owner's choice), not a container.** The
  containers reach it through `host.docker.internal` (`DOCKER_DB_HOST`/`DOCKER_DB_PORT` can
  override this). The spec's `pgvector/pgvector` service is kept under the `docker-db` profile on
  host port 5433, because the host PostgreSQL already uses 5432. pgvector is not needed until
  Phase 7 (`transcript_chunks`). At that point either install pgvector into the host PostgreSQL or
  switch to the `docker-db` service.
- **Laravel runs on the host with `composer run dev` (owner's choice); Docker runs only Redis and
  the AI service.** We tried running everything in Docker first. With the project on a Windows
  drive, every PHP file read crosses Docker Desktop's slow file share: pages took 6–13 s, or about
  1 s with a 60 s opcache revalidation that delays code changes. On the host, pages take about
  0.5 s and edits show up immediately. Consequences:
  - Laravel talks to Redis through `predis/predis` (pure PHP), because XAMPP's PHP has no
    `phpredis` extension. The Docker image still uses `phpredis`.
  - Horizon needs `pcntl`, which Windows lacks. `AppServiceProvider::configureDevCommands()` drops
    it from `composer run dev` on Windows, so the framework's `queue:listen` runs instead, and adds
    `schedule:work`. Horizon is still installed, admin-gated and used in containers and in
    production.
  - The AI worker calls Laravel back at `host.docker.internal:8000` (`LARAVEL_CALLBACK_BASE`).
  - Media are shared through `storage/app/media` (bind-mounted at `/data/storage` in the AI
    containers) instead of a named volume. From Phase 1, Laravel sends paths relative to that root.
  - Reverb listens on 8081 (`REVERB_SERVER_PORT`/`VITE_REVERB_PORT`) because XAMPP Apache uses
    8080.
  - The spec's Laravel containers are kept under the `docker-laravel` profile. They use
    `artisan serve --no-reload`, because without it serve drops the container's environment and
    re-reads `.env`. They also use the opcache settings in `docker/php/php.ini`.
  - Production still needs PHP-FPM/Octane behind a web server, with Horizon.
- **Composer `config.platform` declares `ext-pcntl` and `ext-posix`.** Horizon needs them, but they
  don't exist on Windows. They are installed in the Docker image, which is the only place Horizon
  runs.
- **The Laravel Redis cache uses db 3.** Laravel's default cache db (1) is the Celery broker db in
  the spec (§5).
- **Every Docker host port is bound to `127.0.0.1`**: Redis, the AI API, and the optional
  PostgreSQL and MinIO.
- **Both images run as UID 1000**, so either side can write to the shared media folder when
  Laravel also runs in Docker.
- **Both images are kept minimal for Phase 0.** The AI image has no `ffmpeg` yet: it pulls several
  hundred MB of packages and the download timed out on the dev connection. It comes back in Phase 1
  with the probe stage. The PHP image installs only the PHP extensions it needs (no `gd`, git or
  Composer), because Composer runs on the host.
- **Python dependencies are Phase 0 only** (FastAPI, uvicorn, pydantic-settings, httpx, Celery).
  The media and AI libraries in §4 are added in the phase that uses them, which keeps the image
  small until then. The image installs the `dev` extra so `pytest`, `ruff` and `mypy` run inside the
  container.

### Signed internal calls

- **The scheme matches §7 exactly.** `X-Timestamp` plus `X-Signature: sha256=HMAC(secret,
  ts + "." + raw_body)`, with a ±5 minute window. A fixed test signature is checked by both
  `tests/Unit/Services/InternalSignatureTest.php` and `ai-service/tests/test_security.py`.
- **Added replay protection.** Each signature is accepted only once within the window: Laravel
  uses `Cache::add`, Python uses Redis `SET NX`. Retries re-sign with a new timestamp, so they still
  work.
- **Python `/health` is unsigned** so the Docker health check can call it. It reveals only the
  status, version and whether a GPU is present. Laravel still signs its `/health` request. Every
  other Python route requires a signature.
- **Both sides share one secret.** Compose passes `INTERNAL_SHARED_SECRET` from the root `.env` to
  the AI containers, so the two values can't drift apart.
- **Internal routes have their own `internal` middleware group** (`routes/internal.php`): throttle,
  signature check, bindings. They have no session or CSRF protection, and errors are returned as
  JSON.
- **The admin "ping the AI service" button calls Python inside the request**, with a 10 s timeout
  and a 3 s connect timeout, rather than queueing the call. It is a diagnostics button: the admin
  gets an immediate error if `ai-api` is down. Real pipeline calls (Phase 1) are queued.

### Realtime and Horizon

- **Echo authorises private channels through axios**, using the XSRF cookie, instead of a
  `csrf-token` meta tag. A meta tag would go stale after an Inertia login, because the HTML isn't
  reloaded.
- **`SystemPingReceived` broadcasts through the queue** (`ShouldBroadcast`), so one test ping also
  proves Horizon is working.
- **Horizon is admin-only in every environment, including `local`.** The package default lets
  everyone in on `local`, and the dev stack is reachable on the network. Guests are redirected to
  the login page.

### Roles and accounts

- **Added the `role` middleware and the per-role route files now**, rather than in Phase 1,
  because the admin System status page and Horizon need them. Encoder and user route files are
  empty until their phases.
- **The shared Inertia prop `auth.user` contains only** `id`, `name`, `email`, `role` and
  `email_verified_at`, not the whole model.
- **`UserSeeder` reads the admin from `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD`.** It is safe to
  re-run: it keeps changed passwords and restores the admin role and active status. Outside
  local/testing it requires `ADMIN_PASSWORD` and skips the demo encoder and viewer. It does not use
  factories, because Faker isn't installed in production.
- **Public registration can be turned off** with `ALLOW_PUBLIC_REGISTRATION=false`, which removes
  the Fortify feature. Sign-up always creates `user` (viewer) accounts. Note: Wayfinder then stops
  generating the `register` route, so the frontend imports that use it must be guarded before
  registration is switched off in a build.

### Frontend

- **Redesigned the welcome, login and registration pages.** They use a new film-frame logo, a teal
  `brand` colour token and a dark `ink` token (light and dark themes), and a split-screen auth
  layout with a brand panel. The starter kit's Laravel links were removed from the sidebar.
- **Main bundle size.** The main bundle is about 700 kB minified. Echo and Pusher add about 100 kB
  of it. Loading Echo only on pages that subscribe is a follow-up.
