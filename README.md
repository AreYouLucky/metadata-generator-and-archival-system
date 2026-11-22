# Metadata Generator and Archival System

https://claude.ai/artifact/PbdfWN27oYKpk5pEL6wCKv

A web app for building a video archive. Encoders upload videos; the system transcribes them,
analyses them with AI, and produces editable metadata (titles, descriptions, tags, chapters,
quotes) plus a full archival record with checksums. An admin approves each record, and approved
videos appear in a public catalogue that anyone can browse by date, event and category.

> **Status:** Phase 4 (approvals, usage and public catalogue) done. Phases 1–3 built users,
> chunked uploads, checksums, local GPU transcription, the synced workspace, every platform
> preset, translations with captions, six export formats and the full archival record (Archive
> tab, accession numbers, access copies, fixity checks, works, embargoes and the distribution
> policy). Phase 4 adds the approval workflow (submit, request changes, reject, approve, reopen),
> an admin overview, usage page with CSV and daily processing limits, admin lists of genres and
> video types, and the public catalogue (home, browse, project and series pages, watch page)
> with access logging. The full design is in [PROJECT_SPEC.md](PROJECT_SPEC.md) and is built
> phase by phase (see [Roadmap](#roadmap)).

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

| Layer                | Technology                                                                                              |
| -------------------- | ------------------------------------------------------------------------------------------------------- |
| Web app              | Laravel 13, Inertia v3, React 19, TypeScript, Tailwind 4, shadcn/ui                                     |
| Realtime and queues  | Laravel Reverb + Echo, Redis, Laravel Horizon                                                           |
| Database             | PostgreSQL with pgvector                                                                                |
| AI and media service | Python 3.12+, FastAPI, Celery, ffmpeg/ffprobe                                                           |
| AI providers         | Transcription: local faster-whisper on the GPU (default) or OpenAI. Metadata: OpenAI (see DECISIONS.md) |
| Local development    | Docker Compose                                                                                          |

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
3. Configure the AI service: copy `ai-service/.env.example` to `ai-service/.env` and set
   `OPENAI_API_KEY` there (never in the root `.env`).
4. Start Docker Desktop, then build and start Redis and the AI service. With the NVIDIA GPU (the
   default local transcription):
    ```bash
    docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d --build
    ```
    Without a GPU, set `LOCAL_WHISPER_DEVICE=cpu` and `LOCAL_WHISPER_COMPUTE_TYPE=int8` in
    `ai-service/.env` and run `docker compose up -d --build`. The first build downloads ffmpeg and
    the CUDA libraries (a few GB); the first transcription downloads the Whisper model (~3 GB),
    which is kept in the `whispermodels` volume.
5. Create the tables and seed data:
    ```bash
    php artisan migrate --seed
    ```

### Every day

```bash
docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d   # Redis + AI service
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

| Service         | What it runs                                                                                               | Host port        |
| --------------- | ---------------------------------------------------------------------------------------------------------- | ---------------- |
| `redis`         | Laravel queues (db 0), Celery broker (db 1) and results (db 2), Laravel cache (db 3)                       | `127.0.0.1:6379` |
| `ai-api`        | FastAPI (`/health`, signed `/ping`, `/jobs/process`, `/generate/metadata`)                                 | `127.0.0.1:8001` |
| `ai-worker`     | Celery: checksums, probing, audio extraction, LLM calls; calls Laravel back at `host.docker.internal:8000` | —                |
| `ai-worker-gpu` | Celery `gpu` queue: transcription, one job at a time (the 6 GB GPU holds one model)                        | —                |

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

## Try Phase 1

1. As the admin, open **Users** and create an encoder account.
2. Sign in as the encoder and open **Projects**: create the event (and a series such as "Day 1"
   if it has sessions). Every video is filed under a project.
3. Open **New record** (or **Upload to this project** on the project page), and drop, paste or
   import a video into the media bin (a 5-minute MP4 is a good first test). It plays in the
   preview before upload. In the inspector, pick the project (or create it there), optionally a
   series, and **YouTube** or **Generic**.
4. The workspace opens like a video editor and shows each step live in the strip under the
   toolbar: checksums, technical metadata, audio extraction, transcription and AI metadata.
5. Play the video: the transcript (left panel) and the timeline (bottom: chapters, transcript
   and waveform tracks) follow along. Drag on the time ruler to scrub, zoom the timeline, click
   a line to seek, search the transcript, pause to copy the current line, and edit a line
   (Enter saves).
6. Edit the metadata and save, or regenerate it with another platform or instructions.
7. **Export JSON** and check `technical.files[0]`: codecs, both checksums, and a SHA-256 that
   matches `certutil -hashfile <file> SHA256` (Windows) or `sha256sum <file>`.
8. Sign in as a second encoder: opening the first encoder's record URL returns 403.

Token use is kept small on purpose (see DECISIONS.md, "Token budget"): the audio never leaves the
machine, the transcript is sent once in compact form, and regenerating reuses a cached summary.

## Try Phase 2

After pulling Phase 2, run `php artisan migrate`, then restart the AI containers so they load
the new code (`docker compose restart ai-api ai-worker`). Rebuild the AI image
(`docker compose build ai-api`) only if you want its `pyproject.toml` (lint settings) updated too.

1. Open a processed record. In the **Metadata** tab, click **Regenerate**, pick **TikTok** and
   regenerate: the caption is limited to 2,200 characters and gets 3–5 hashtags.
2. Under the titles, click **Generate more**: three new options are written from the summary
   (not the transcript) and appear in the list, already saved.
3. Scroll down: the tone and content flags, and the **Key quotes** (click one to play it).
4. Open the **Languages** tab and translate into Filipino (or another language). Progress shows
   live; when it is ready, turn on its captions (the CC button there, or the captions menu in
   the transport bar) and play the video.
5. Open **Export** (the inspector tab, or the header button): switch between JSON, SRT, VTT, TXT,
   CSV and Markdown, pick the translation as the language, watch the preview, and download.
6. Open **Settings → Export schemas**, create a schema with, say, Title, AI description and Tags,
   then pick it in the Export panel: the JSON keeps only those fields.
7. Press **?** in the workspace for the keyboard shortcuts: Space/K, J/L, ←/→ for lines, C and
   Shift+C to copy the current line, / to search, E to edit the current line.

## Try Phase 3

After pulling Phase 3: run `php artisan migrate`, then `php artisan archive:assign-accession-numbers`
once (it numbers the records made before Phase 3, oldest first), and restart the AI containers
(`docker compose restart ai-api ai-worker ai-worker-gpu`). Fixity checks run daily at 02:00 through
the scheduler (`schedule:work`, part of `composer run dev`).

1. Upload a ProRes or MXF master. After the transcript and metadata, the **Access copy** step makes
   an H.264 MP4 and the player switches to it. **Archive → Technical** shows scan type, bit depth,
   chroma, colour and audio tracks for both the master and the access copy, with their checksums.
2. **Archive → Descriptive:** confirm or reject the AI's subject and keyword suggestions (dashed
   chips); if the file has a creation date, the hint offers it as the recording date (with a
   warning, since it's often the copy date).
3. **Archive → Location:** pick region → province → city → barangay, or apply the AI's suggestion
   or the file's GPS tag.
4. **Archive → Rights:** set access, permission and an embargo date, then save: the embargo badge
   appears in the header and in the record list. Upload a release form (stored privately).
5. **Archive → Provenance:** record a camera-card transfer, then **Check now**: the result appears
   under Fixity checks and in the history. A changed or missing file is flagged and emailed to the
   owner and admins (`MAIL_MAILER=log` writes the email to the log).
6. **Archive → Structure:** create a work, file this record as Reel 1, and another record as Reel 2.
7. **Archive → Archival:** set a classification other than unclassified: the badge appears, and
   the record list's **Classified** filter finds it. Search the list by accession number.
8. Click **TC** in the transport bar to show timecode (from the master's start timecode) instead
   of the clock. **Export → JSON** contains every archival block.
9. As an admin, set a past disposition date on a record and open **Disposition review**.

## Try Phase 4

After pulling Phase 4: run `php artisan migrate` (one new index) and `npm run build` (or keep
`composer run dev` running). The public catalogue is at `/catalogue`; the landing page at `/`
links to it. Set `ALLOW_PUBLIC_REGISTRATION=false` to hide sign-up, and
`PUBLIC_CATALOGUE_REQUIRES_LOGIN=true` to keep guests out.

1. As an encoder, open a finished record (rights are public by default; set **Archive → Rights**
   to Internal or Restricted to keep one off the site) and click **Submit** in the header. If AI
   subject or keyword suggestions are still unconfirmed, the dialog offers to confirm them (or use
   **Confirm all** in Archive → Descriptive). The record is now read-only for you and appears under
   **Pending reviews**.
2. As an admin, open **Approvals** and pick the record. Watch it and go through the tabs, all
   tables: **Overview**, **Transcript** (filter, click a time to jump), **Metadata** (titles,
   description, tags, chapters, quotes, tone), **Archive** (every Archive section, files,
   fixity) and **Translations** (view each one's lines). The box on the right says whether
   approving will publish it (or what keeps it off the public site). Choose **Request changes**
   with a note.
3. As the encoder, click the status button in the header (**Changes requested**) to read the
   note, fix the record and submit again.
4. As the admin, **Approve** it. Open `/browse` in a private window: the video is there under its
   project, series and date (try the year → month timeline and the filters). Open it: it plays
   (from the access copy when the master can't play in browsers), with captions, chapters and the
   transcript, and none of the internal fields (accession number, rights notes, checksums,
   unconfirmed suggestions).
5. In the workspace, click the status button → **Access log**: the public view is listed with
   its IP address.
6. As the admin, click the status button → **Reopen as draft**. Reload the public page: the video
   is gone. Records with an embargo in force, or a classification other than unclassified, stay
   hidden even when approved.
7. In **Users**, give an encoder a daily limit of 1 minute. Once their transcription minutes for
   today reach it, the Import page says the limit is used up and **Upload and process** (and
   **Retry**) are refused.
8. Open **Usage**: minutes, tokens and estimated cost per encoder and per record for a date
   range; click an encoder to narrow the record table, and **Download CSV**. **Overview** sums up
   approvals, the processing queue and this month's usage.
9. Add, rename and reorder entries in **Genres** and **Video types**; ones that records use can't
   be deleted.

## Roadmap

| Phase                              | Delivers                                                                                                                    |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| 0 — Scaffold                       | Docker Compose stack, Python service skeleton, signed Laravel ↔ Python calls, realtime test event                           |
| 1 — Core app                       | Roles and user management, upload, checksums, technical metadata, transcription, AI metadata, synced workspace, JSON export |
| 2 — More AI output                 | All presets, title options, quotes, translations, SRT/VTT/TXT/CSV/Markdown export, captions, shortcuts                      |
| 3 — Archival metadata              | Descriptive, rights, provenance and structural records; proxies, versions, fixity checks, embargoes                         |
| 4 — Approvals and public catalogue | Approval workflow, usage dashboard and limits, categories and events, public browse and watch pages                         |
| 5 — Visual analysis                | Shots with timecode, key frames, frame descriptions, OCR, object labels                                                     |
| 6 — Workflow                       | Batch upload and ZIP download, URL import, speaker labels, AssemblyAI transcription                                         |
| 7 — Advanced                       | Clips, chat with the video, public API, YouTube publishing                                                                  |

Each phase ends with passing tests, an updated README, and sign-off before the next one starts.

## Testing and code quality

```bash
composer test        # Pint style check, PHPStan, and the PHP test suite (SQLite in memory)
npm run check        # frontend lint and format check
npm run types:check  # TypeScript type check

docker compose exec ai-api pytest        # Python tests
docker compose exec ai-api ruff check .  # Python lint
docker compose exec ai-api mypy app      # Python types (strict)
```

Both test suites check the same fixed HMAC signature, so a change to the signing scheme on one
side fails the other side's tests.

`pyproject.toml` is baked into the AI image (only `app/` and `tests/` are mounted), so after
changing it, rebuild the image or copy it in before running ruff and mypy in the container.

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
