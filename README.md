# Metadata Generator and Archival System

https://claude.ai/artifact/PbdfWN27oYKpk5pEL6wCKv

A web app for building a video archive. Encoders upload videos; the system transcribes them,
analyses them with AI, and produces editable metadata (titles, descriptions, tags, chapters,
quotes) plus a full archival record with checksums. An admin approves each record, and approved
videos appear in a public catalogue that anyone can browse by date, event and category.

> **Status:** Phase 0 (scaffold) done: the Docker stack, the database schema, signed
> Laravel ↔ Python calls, live updates and the welcome, login and registration pages. The full
> design is in [PROJECT_SPEC.md](PROJECT_SPEC.md) and is built phase by phase (see
> [Roadmap](#roadmap)).

---

## Who uses it

| Role              | What they do                                                                                                                                               |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Admin**         | Manages users, reviews and approves content, monitors usage and sets limits, manages categories and events. Has access to every feature, on every project. |
| **Encoder**       | Uploads videos, reviews and edits the transcript and metadata, completes the archival record, submits it for approval.                                     |
| **User** (public) | Browses, searches and watches approved videos, organised by date, event, category and series. Guests can browse too unless login is required.              |

## What it does

**Processing**

- Upload (drag-and-drop, paste, URL import, batches), with checksums and full technical metadata
  captured at ingest.
- Transcription with timestamps, language detection and speaker labels.
- Visual analysis: shot detection, key frames, frame descriptions, on-screen text (OCR), object
  labels and face counts (no face recognition).
- AI-generated titles, descriptions, tags, keywords, hashtags, chapters, quotes and tone, with
  platform presets (YouTube, TikTok, Instagram, LinkedIn, generic).

**Workspace**

- Video player synced with the transcript: click to seek, pause to copy the current line,
  search, inline editing, captions, keyboard shortcuts.
- Translations, chat with the video (answers cite timestamps), clip cutting.

**Archive**

- Five kinds of metadata per video: descriptive, technical, administrative/rights,
  preservation/provenance and structural.
- MD5 and SHA-256 checksums, scheduled fixity checks, and an append-only log of everything that
  has happened to each file.
- Versions (master, mezzanine, proxy), multi-reel works, sidecar files, release forms, embargoes.
- Shot and scene segments with SMPTE timecode in and out.

**Distribution**

- Admin approval before anything goes public.
- Public catalogue: browse by date, event, category and series, then watch with captions,
  chapters and transcript.
- Exports: JSON (default), SRT, VTT, TXT, CSV, Markdown, plus custom JSON export schemas.
- Publish to YouTube, and a token-based public API.

## Architecture

```
            Browser (React + Inertia)
                     │   ▲  live progress (Reverb WebSockets)
                     ▼   │
┌────────────────────────────────────────┐        signed HTTP         ┌─────────────────────────┐
│ Laravel  — the product                 │ ─────────────────────────▶ │ Python AI service       │
│ users, roles, approvals, database,     │                            │ FastAPI + Celery        │
│ uploads, UI, queues (Horizon),         │ ◀───────────────────────── │ ffmpeg, transcription,  │
│ exports, public API, YouTube           │   signed result callbacks  │ vision, OCR, LLM calls  │
└───────────────┬────────────────────────┘                            └────────────┬────────────┘
                │                                                                   │
        PostgreSQL + pgvector          Redis (queues, Celery broker)        shared file storage
```

- **Laravel** owns all data and the user-facing app.
- **Python** is a stateless worker for media and AI work. It never touches the database; it reads
  and writes files in shared storage and reports back to Laravel through HMAC-signed callbacks.

| Layer                | Technology                                                                                                    |
| -------------------- | ------------------------------------------------------------------------------------------------------------- |
| Web app              | Laravel 13, Inertia v3, React 19, TypeScript, Tailwind 4, shadcn/ui                                           |
| Realtime and queues  | Laravel Reverb + Echo, Redis, Laravel Horizon                                                                 |
| Database             | PostgreSQL with pgvector                                                                                      |
| AI and media service | Python 3.12+, FastAPI, Celery, ffmpeg/ffprobe                                                                 |
| AI providers         | Configurable: OpenAI (transcription, embeddings), Anthropic Claude (metadata, vision, chat), or local Whisper |
| Local development    | Docker Compose                                                                                                |

## Getting started

Laravel runs directly on your machine with `composer run dev`. Docker runs only what can't run on
Windows: **Redis** (queues, cache, Celery broker) and the **Python AI service** (API and worker).

Requirements: PHP 8.3+ with Composer (for example XAMPP), Node.js 22+, PostgreSQL, and Docker
Desktop.

### First-time setup

1. Create the database and its owner in PostgreSQL:
    ```sql
    CREATE USER mgas WITH PASSWORD 'choose-a-password';
    CREATE DATABASE mgas OWNER mgas;
    ```
2. Install and configure:
    ```bash
    composer install
    npm install
    cp .env.example .env    # fill in DB_*, INTERNAL_SHARED_SECRET and the REVERB_* keys
    php artisan key:generate
    ```
3. Start Docker Desktop, then build and start Redis and the AI service:
    ```bash
    docker compose up -d --build
    ```
4. Create the tables and seed data:
    ```bash
    php artisan migrate --seed
    ```

### Every day

```bash
docker compose up -d     # Redis + AI service (if they aren't already running)
composer run dev         # Laravel, queue worker, Reverb, scheduler and Vite
```

Open `http://localhost:8000`. Stop with Ctrl+C. `docker compose stop` stops the containers.

If you edit `.env`, restart `composer run dev`. Its processes keep the values they started
with.

`composer run dev` starts:

| Process     | Command                              | Notes                                                                          |
| ----------- | ------------------------------------ | ------------------------------------------------------------------------------ |
| `server`    | `php artisan serve`                  | `http://localhost:8000`                                                        |
| `queue`     | `php artisan queue:listen` (Windows) | Horizon replaces it on Linux/macOS, where `pcntl` exists                       |
| `reverb`    | `php artisan reverb:start`           | WebSockets on `REVERB_SERVER_PORT` (8081 here, because XAMPP Apache uses 8080) |
| `scheduler` | `php artisan schedule:work`          |                                                                                |
| `vite`      | `npm run dev`                        |                                                                                |

### Accounts

The seeders load all Philippine regions, provinces, cities and barangays, the starter genres and
video types, and these accounts:

| Account               | Role    | Password                                   |
| --------------------- | ------- | ------------------------------------------ |
| `ADMIN_EMAIL` in .env | admin   | `ADMIN_PASSWORD`, or `password` when blank |
| `encoder@example.com` | encoder | `password` (local and testing only)        |
| `user@example.com`    | user    | `password` (local and testing only)        |

Outside `local`/`testing`, `UserSeeder` creates only the admin and refuses to run without
`ADMIN_PASSWORD`.

> Never run `php artisan migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` or
> `db:wipe`. Use `php artisan migrate` for new migrations.

### Docker services

| Service     | What it runs                                                                         | Host port        |
| ----------- | ------------------------------------------------------------------------------------ | ---------------- |
| `redis`     | Laravel queues (db 0), Celery broker (db 1) and results (db 2), Laravel cache (db 3) | `127.0.0.1:6379` |
| `ai-api`    | FastAPI (`/health`, signed `/ping`)                                                  | `127.0.0.1:8001` |
| `ai-worker` | Celery worker; calls Laravel back at `host.docker.internal:8000`                     | —                |

Uploaded media are stored in `storage/app/media`, which is mounted into the AI containers at
`/data/storage`. Optional profiles:

- `--profile docker-laravel` runs Laravel in containers too (`web`, `horizon`, `reverb`,
  `scheduler`). Set `LARAVEL_CALLBACK_BASE=http://web:8000`. It's slow when the project is on a
  Windows drive.
- `--profile docker-db` runs PostgreSQL 17 + pgvector on port 5433.
- `--profile s3` runs MinIO.
- For an NVIDIA GPU, add `-f docker-compose.gpu.yml`.

### Check that everything is connected

Log in as the admin and open **System status** in the sidebar (`/admin/system`):

1. **AI service** shows _Reachable_: Laravel called the Python `/health` endpoint with a signed
   request.
2. **Broadcast from Laravel** adds a ping to the list: queue → Reverb → your browser.
3. **Round trip through the AI service**: Laravel → signed `/ping` → Celery worker → signed
   callback to `/internal/ping` → queue → Reverb → your browser.

If a ping never arrives, check the `queue` and `reverb` output of `composer run dev` and
`docker compose logs ai-worker`.

## Roadmap

| Phase                              | Delivers                                                                                                                    |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| 0 — Scaffold                       | Docker Compose stack, Python service skeleton, signed Laravel ↔ Python calls, realtime test event                           |
| 1 — Core app                       | Roles and user management, upload, checksums, technical metadata, transcription, AI metadata, synced workspace, JSON export |
| 2 — More AI output                 | All presets, title options, quotes, translations, SRT/VTT/TXT/CSV/Markdown export, captions, shortcuts                      |
| 3 — Archival metadata              | Descriptive, rights, provenance and structural records; proxies, versions, fixity checks, embargoes                         |
| 4 — Approvals and public catalogue | Approval workflow, usage dashboard and limits, categories and events, public browse and watch pages                         |
| 5 — Visual analysis                | Shots with timecode, key frames, frame descriptions, OCR, object labels                                                     |
| 6 — Workflow                       | Batch upload and ZIP download, URL import, speaker labels, local transcription                                              |
| 7 — Advanced                       | Clips, chat with the video, public API, YouTube publishing                                                                  |

Each phase ends with passing tests, an updated README, and sign-off before the next one starts.

## Testing and code quality

```bash
composer test        # Pint style check, PHPStan, and the PHP test suite (SQLite in memory)
npm run check        # frontend lint and format check
npm run types:check  # TypeScript type check

docker compose exec ai-api pytest        # Python tests
docker compose exec ai-api ruff check .  # Python lint (and: mypy app)
```

Both test suites check the same fixed HMAC signature, so a change to the signing scheme on one
side fails the other side's tests.

## Project documents

- [PROJECT_SPEC.md](PROJECT_SPEC.md) — the full design and the source of truth: data model,
  routes, pipeline, JSON schema, phases.
- [AGENTS.md](AGENTS.md) — coding conventions for anyone (human or AI) working in the repo:
  role-based folders, one component per file, FormRequest validation, TanStack Query hooks,
  security and performance rules.
- [DECISIONS.md](DECISIONS.md) — choices made during the build and any deviation from the spec.

## Security and privacy

- Never commit `.env` files. API keys stay on the server and are never sent to the browser.
- When hosted AI providers are selected, video audio, frames and transcripts are sent to those
  providers. The app says so in the UI.
- No face recognition: the system stores face counts only. People are identified only from what
  is said or written.
- Release forms and other private attachments are stored privately and are never public.
- Never run `php artisan migrate:fresh` (or other commands that wipe the database) against a
  database with real data.
