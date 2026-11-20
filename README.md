# Metadata Generator and Archival System

https://claude.ai/artifact/PbdfWN27oYKpk5pEL6wCKv

A web app for building a video archive. Encoders upload videos; the system transcribes them,
analyses them with AI, and produces editable metadata (titles, descriptions, tags, chapters,
quotes) plus a full archival record with checksums. An admin approves each record, and approved
videos appear in a public catalogue that anyone can browse by date, event and category.

> **Status:** planning complete, build not started. This repository currently contains the
> Laravel 13 React starter kit. The full design is in [PROJECT_SPEC.md](PROJECT_SPEC.md) and is
> built phase by phase (see [Roadmap](#roadmap)).

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

### Right now (starter kit only)

Requirements: PHP 8.3+, Composer, Node.js 22+.

```bash
composer run setup   # installs dependencies, creates .env, generates the app key, migrates, builds assets
composer run dev     # starts the Laravel server, queue worker and Vite
```

The app runs at `http://localhost:8000` using SQLite.

### After Phase 0 (planned)

The whole stack (Laravel, Vite, Horizon, Reverb, scheduler, PostgreSQL, Redis, the Python API and
worker, optional MinIO) will start with:

```bash
docker compose up
```

These instructions will be updated when Phase 0 is done.

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
composer test        # Pint style check, PHPStan, and the PHP test suite
npm run check        # frontend lint and format check
npm run types:check  # TypeScript type check
```

## Project documents

- [PROJECT_SPEC.md](PROJECT_SPEC.md) — the full design and the source of truth: data model,
  routes, pipeline, JSON schema, phases.
- [AGENTS.md](AGENTS.md) — coding conventions for anyone (human or AI) working in the repo:
  role-based folders, one component per file, FormRequest validation, TanStack Query hooks,
  security and performance rules.
- `DECISIONS.md` — will record any deviation from the spec (created in Phase 0).

## Security and privacy

- Never commit `.env` files. API keys stay on the server and are never sent to the browser.
- When hosted AI providers are selected, video audio, frames and transcripts are sent to those
  providers. The app says so in the UI.
- No face recognition: the system stores face counts only. People are identified only from what
  is said or written.
- Release forms and other private attachments are stored privately and are never public.
- Never run `php artisan migrate:fresh` (or other commands that wipe the database) against a
  database with real data.
