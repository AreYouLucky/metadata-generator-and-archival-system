# Metadata Generator and Archival System — Project Spec

> **For the AI coding agent reading this:** This file is the source of truth for the project.
> Build it **phase by phase** (see §13 Build phases). At the end of each phase, stop,
> summarise what was built, list how to run and test it, and wait for approval before starting the
> next phase. Do not skip ahead. If something in this spec is unclear or conflicts with reality
> (a package API changed, a version is different), pick the most sensible option, note it in
> `DECISIONS.md`, and keep going.

---

## 1. What we're building

A web app that takes a video (upload or URL), transcribes it, analyses it with AI, and produces
rich, editable metadata that can be exported (JSON, SRT, VTT, TXT, CSV, Markdown) or published
directly to YouTube.

It is also an **archival system**. Every video gets a full catalogue record covering five kinds of
metadata (descriptive, technical, administrative/rights, preservation/provenance and structural, see
§11), with checksums and fixity checks so the archive can prove its files have not changed.

### Terminology

| Term | Meaning | Table |
|---|---|---|
| **Record** | One archived video and everything known about it. | `records` |
| **Project** | An event or programme that groups records (e.g. a conference, a campaign). | `projects` |
| **Project series** | A series or sub-event inside a project (e.g. one session, one episode run). | `project_series` |
| **Record file** | One stored file of a record: master, mezzanine, proxy or access copy. | `record_files` |
| **Work** | The reels or parts of one work; each part is its own record. | `works` |
| **Agency / unit** | The producing agency and its production unit (division). | `production_agencies` |

Core user flow:

1. An encoder uploads a video (drag-and-drop, paste, or URL import) — one or many (batch) — and
   optionally files it under a project and series.
2. Backend stores the file, computes checksums, and captures full technical metadata.
3. Backend extracts audio, transcribes with timestamps, detects language, labels speakers.
4. Backend analyses frames: scenes, thumbnails, frame descriptions, on-screen text, objects.
5. AI generates title options, description, tags, keywords, hashtags, chapters, quotes, tone/flags,
   and suggests descriptive fields (subjects, keywords, location).
6. The encoder reviews everything in a workspace: video player synced with transcript, editable fields.
7. The encoder completes the archival record: confirms suggestions, enters credits, rights,
   provenance, location (region → province → city/municipality → barangay), classification and
   retention, and links versions, reels and attachments.
8. The encoder can pause the video and copy the current transcript line, search, edit, translate,
   chat with the video, cut clips.
9. The encoder exports metadata (JSON by default) or publishes to YouTube.
10. The encoder submits the record for approval; once an admin approves it, it appears in the public
    catalogue (if its classification and rights allow).

### Roles

| Role | Who | Can do |
|---|---|---|
| `admin` | System managers | Everything an encoder can do, on **every** record, plus: manage users (create encoders and admins, change roles, deactivate), review and approve or reject submitted records, view usage and set limits, manage agencies and units, genres and video types. |
| `encoder` | Staff who add content | Upload and process videos, edit transcripts and metadata, complete the archival record, create projects and series, submit records for approval. Sees and edits only the records they created. |
| `user` | Public viewers | Browse, search and watch approved public records, organised by date, project (event), series, genre, video type, agency and region. No access to the workspace or to internal metadata. |

- Guests (not logged in) can also browse the public catalogue unless
  `PUBLIC_CATALOGUE_REQUIRES_LOGIN=true`. Public sign-up creates `user` accounts only; encoders and
  admins are created by an admin.
- **Approval workflow:** `draft` → encoder submits → `submitted` → admin chooses `approved`,
  `changes_requested` or `rejected` (with notes). `changes_requested` goes back to the encoder as
  editable. Approved records are read-only for encoders; an admin can edit them, or reopen them as
  `draft`, which removes them from the public catalogue until they are approved again. Every review
  action (with its notes) is logged in `video_audit_logs`.
- **A record is public only when** it is approved and published, its security classification is
  `unclassified`, and its rights allow public access (access level `public`, permission
  `approved`, no embargo in force). See the distribution policy in §8.
- **The public view shows only:** the access copy (proxy, or the master if it is browser-playable;
  never a master download), title, alternative title, description, date recorded, project, series,
  genre, video type, agency, location names, confirmed subjects and keywords, credits, chapters,
  transcript/captions, licence and usage restrictions. It never shows rights notes, release forms,
  provenance, checksums, unconfirmed AI suggestions, classification details or usage data.

---

## 2. Tech stack

| Layer              | Choice                                                                                                                     |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| Main app           | **Laravel 13** with the official **React starter kit** (Inertia + React + TypeScript + Tailwind + shadcn/ui)               |
| Frontend           | React + TypeScript via **Inertia.js**, Tailwind CSS, shadcn/ui, lucide-react, TanStack Query + axios (see AGENTS.md)      |
| Realtime           | **Laravel Reverb** (WebSockets) + **Laravel Echo** on the frontend                                                         |
| Laravel queues     | **Redis** + **Laravel Horizon**                                                                                            |
| Database           | **PostgreSQL** with the **pgvector** extension (Laravel's native `vector` column and `whereVectorSimilarTo`)               |
| AI / media service | **Python 3.12+**, **FastAPI**, **Celery** (Redis broker)                                                                   |
| Media tools        | **ffmpeg / ffprobe** (installed in the Python container)                                                                   |
| File storage       | Shared volume in dev; S3-compatible (S3 / Cloudflare R2 / MinIO) in prod                                                   |
| Auth               | Starter kit auth (Fortify) + **Laravel Sanctum** (public API tokens)                                                       |
| Local dev          | **Docker Compose**                                                                                                         |
| Tests              | PHPUnit (Laravel), pytest (Python), Vitest (optional, frontend)                                                            |
| Lint/format        | Laravel Pint, PHPStan (level 7), ESLint + Prettier, ruff + mypy                                                            |

### Responsibility split

- **Laravel = the product.** Users, auth, records, projects, database, uploads, storage, UI, queues,
  realtime events, exports, public API, YouTube OAuth/publishing.
- **Python = the brain.** Stateless worker service for anything media- or AI-heavy: probing,
  checksums, audio extraction, transcription, diarization, translation, proxies, frame/scene
  analysis, OCR, object detection, embeddings, LLM calls, clip cutting, URL downloads.
- **Python never touches the database.** It receives inputs over HTTP, reads/writes files in
  shared storage, and reports progress/results back to Laravel via signed callbacks.

---

## 3. Repository layout

The Laravel app lives at the repository root; the Python service is in `ai-service/`.

```
metadata-generator-and-archival-system/
├── PROJECT_SPEC.md          # this file
├── AGENTS.md                # coding conventions (role folders, partials, FormRequests, hooks...)
├── DECISIONS.md             # agent logs deviations/choices here
├── README.md                # setup + run instructions
├── docker-compose.yml
├── app/
│   ├── Enums/               # ReviewStatus, ProcessingStatus, FileRole, SecurityClassification...
│   ├── Events/              # RecordProgressUpdated, RecordStageCompleted, BatchProgressUpdated
│   ├── Exporters/           # JsonExporter, SrtExporter, VttExporter, TxtExporter, CsvExporter, MarkdownExporter
│   ├── Http/
│   │   ├── Controllers/{Admin,Encoder,User,All}/   # one folder per role (see AGENTS.md)
│   │   ├── Controllers/Api/V1/
│   │   ├── Controllers/Internal/                   # callbacks from Python
│   │   ├── Requests/{Admin,Encoder,User,All}/      # FormRequests, mirroring Controllers/
│   │   └── Middleware/VerifyInternalSignature.php
│   ├── Jobs/                # StartProcessingJob, GenerateMetadataJob, TranslateJob, CutClipJob, BuildBatchZipJob...
│   ├── Models/              # Record, RecordFile, Project, ProjectSeries... (one per table, §8)
│   ├── Policies/            # RecordPolicy (incl. distribute), ProjectPolicy...
│   └── Services/AiService/AiServiceClient.php   # typed HTTP client for the Python service
├── database/
│   ├── data/updated_phil.sql                     # Philippine locations (MySQL dump)
│   ├── migrations/
│   └── seeders/             # LocationSeeder loads regions/provinces/cities/barangays
├── resources/js/
│   ├── pages/               # Inertia pages: admin/, encoder/, user/ (public), all/
│   ├── components/          # feature components (see §9), same role folders + partials/
│   ├── hooks/               # TanStack Query hooks, useVideoSync, useKeyboardShortcuts, useRecordChannel...
│   ├── stores/              # zustand stores
│   ├── types/               # shared TS types (mirror the JSON schema in §10)
│   └── lib/                 # utilities.ts (shared helpers), utils.ts (shadcn cn)
├── routes/                  # web.php requires admin.php, encoder.php, user.php, all.php
├── tests/
└── ai-service/              # Python FastAPI + Celery
    ├── app/
    │   ├── main.py          # FastAPI app
    │   ├── config.py        # pydantic-settings
    │   ├── security.py      # HMAC signing/verification
    │   ├── callbacks.py     # report progress/results to Laravel
    │   ├── api/             # routers
    │   ├── worker.py        # Celery app
    │   ├── pipeline/        # one module per stage (see §6)
    │   ├── providers/       # transcription/, llm/, vision/, embeddings/ — swappable backends
    │   ├── prompts/         # prompt templates (plain text / jinja)
    │   └── schemas/         # pydantic models (mirror §10)
    ├── tests/
    ├── pyproject.toml
    └── Dockerfile
```

---

## 4. Dependencies

### Laravel

```bash
composer require laravel/reverb laravel/horizon
composer require google/apiclient             # YouTube Data API (Phase 7)
composer require league/flysystem-aws-s3-v3   # S3/R2 storage (prod)
php artisan install:broadcasting              # sets up Reverb + Echo
php artisan horizon:install
php artisan install:api                       # Sanctum + routes/api.php
```

pgvector needs no extra package: Laravel 13 has a native `vector` column, `vectorIndex()` and
`whereVectorSimilarTo()`. Horizon needs the `pcntl`/`posix` extensions, so it runs in Docker, not on
Windows hosts.

Frontend:

```bash
npm install axios @tanstack/react-query       # data fetching in hooks (AGENTS.md)
npm install laravel-echo pusher-js            # Reverb client
npm install zustand
npm install react-dropzone
npm install @tanstack/react-virtual           # virtualised long transcripts
npm install wavesurfer.js                     # optional waveform/timeline
npm install -D vitest @testing-library/react  # optional
```

Use shadcn/ui components already in the starter kit (Button, Card, Tabs, Dialog, Input,
Textarea, Badge, Progress, DropdownMenu, Tooltip, Sheet). Add more with the shadcn CLI as needed.

### Python (`ai-service/`)

```toml
# pyproject.toml — core
fastapi
uvicorn[standard]
pydantic
pydantic-settings
httpx
celery[redis]
python-multipart
ffmpeg-python
pymediainfo
openai                 # transcription (whisper) + embeddings
anthropic              # Claude for metadata, translation, vision, chat
tenacity               # retries
jinja2                 # prompt templates

# Phase 5+ (heavier — put in an optional extra: [vision])
scenedetect[opencv]
opencv-python-headless
easyocr
ultralytics            # YOLO object detection — NOTE: AGPL-3.0 license, check before commercial use
pillow
numpy

# Phase 6+ (optional extras: [local], [diarization], [import])
faster-whisper         # local transcription
pyannote.audio         # speaker diarization (needs Hugging Face token + model terms accepted)
yt-dlp                 # URL import

# dev
pytest
pytest-asyncio
ruff
mypy
```

System packages in the Python image: `ffmpeg`, `libmediainfo0v5` (or distro equivalent),
`libgl1` (OpenCV).

---

## 5. Docker Compose services

| Service     | Purpose                                                                    |
| ----------- | -------------------------------------------------------------------------- |
| `web`       | Laravel (PHP-FPM + nginx, or `php artisan serve` in dev)                   |
| `vite`      | Vite dev server (dev only)                                                 |
| `horizon`   | `php artisan horizon`                                                      |
| `reverb`    | `php artisan reverb:start`                                                 |
| `scheduler` | `php artisan schedule:work`                                                |
| `postgres`  | `pgvector/pgvector` image                                                  |
| `redis`     | Redis (Laravel queues = db 0, Celery broker = db 1, Celery results = db 2, Laravel cache = db 3) |
| `ai-api`    | `uvicorn app.main:app` (FastAPI)                                           |
| `ai-worker` | `celery -A app.worker worker`                                              |
| `minio`     | Optional S3-compatible storage for prod-like dev                           |

- `web`, `horizon`, `ai-api`, `ai-worker` all mount the **same storage volume** at the same
  path (e.g. `/data/storage`) so file paths can be passed between services.
- `ai-worker` should support an optional GPU profile (`deploy.resources.reservations.devices`)
  for local Whisper / pyannote / YOLO.

---

## 6. Processing pipeline

Laravel creates a `Record` and its master `RecordFile`, stores the file, then dispatches
`StartProcessingJob`, which calls `POST /jobs/process` on the Python service. Python runs a Celery
chain of stages. Each stage:

1. Posts progress to Laravel: `POST {LARAVEL_URL}/internal/records/{id}/progress`
2. Posts its result when done: `POST {LARAVEL_URL}/internal/records/{id}/results/{stage}`
3. On failure, posts `status=failed` with an error message; later stages that depend on it are
   skipped, independent stages continue.

Laravel stores each stage's state in `record_stages`, stores results, and broadcasts
`RecordProgressUpdated` / `RecordStageCompleted` on the private channel `records.{id}` via Reverb.

| #   | Stage               | What it does                                                    | Depends on                           | Phase |
| --- | ------------------- | --------------------------------------------------------------- | ------------------------------------ | ----- |
| 1   | `probe`             | ffprobe + mediainfo → `record_technical_metadata` for the file (§11), plus container tags (creation time, GPS location, start timecode) | —                                    | 1     |
| 2   | `checksum`          | MD5 + SHA-256 of the stored file in one streaming pass; compare with an expected checksum if the encoder supplied one | —                                    | 1     |
| 3   | `extract_audio`     | ffmpeg → mono 16 kHz MP3 (~48 kbps)                             | probe                                | 1     |
| 4   | `transcribe`        | Segments with timestamps + detected language → original `record_transcripts` | extract_audio                        | 1     |
| 5   | `diarize`           | Speaker labels per segment (S1, S2…) → `record_speakers`        | transcribe                           | 6     |
| 6   | `proxy`             | ffmpeg → browser-playable access copy (H.264/AAC MP4, ≤ `PROXY_MAX_HEIGHT`) when the master can't play in a browser; stored as a `proxy` record file, probed and checksummed | probe                                | 3     |
| 7   | `scenes`            | Shot-change timestamps (PySceneDetect) → `record_scenes` with timecode in/out | probe                            | 5     |
| 8   | `frames`            | Extract key frames (scene starts + every N seconds) → `record_frames` | probe, scenes                        | 5     |
| 9   | `vision`            | Frame descriptions via vision LLM                               | frames                               | 5     |
| 10  | `ocr`               | On-screen text per frame (EasyOCR)                              | frames                               | 5     |
| 11  | `objects`           | Object labels + face **count** per frame (YOLO)                 | frames                               | 5     |
| 12  | `embed`             | Chunk transcript (~30–60 s windows) → embeddings                | transcribe                           | 7     |
| 13  | `generate_metadata` | LLM → `record_metadata` (titles, description, tags, chapters, etc.); from Phase 3 also suggested subjects, keywords and location | transcribe (+ vision/ocr if enabled) | 1     |

`probe` and `checksum` also run on every additional record file (mezzanine, proxy, clip), so each
file has its own technical record and checksums.

Pipeline options (chosen on upload): `diarization`, `visual_analysis`, `ocr`, `object_detection`,
`translate_to: string[]`, `preset`, `custom_prompt`.

### Transcription details

- Provider is configurable: `TRANSCRIPTION_PROVIDER=openai|local|assemblyai`.
- **openai**: use the `whisper-1` model with `response_format=verbose_json` and
  `timestamp_granularities=["segment"]` to get segment timestamps (check current OpenAI docs; if a
  newer model returns segment timestamps, prefer it and log it in `DECISIONS.md`).
- The OpenAI upload limit is 25 MB. If the audio is larger, split it into ~10-minute chunks
  with ~2 s overlap, transcribe each, offset timestamps, and de-duplicate the overlap.
- **local**: `faster-whisper` (model size from env, default `small`), word timestamps off by default.
- Normalise all providers to the same `TranscriptSegment` schema.

### LLM details

- Provider is configurable: `LLM_PROVIDER=anthropic|openai`, model from env.
- Always request **structured JSON** (tool use / structured output), validate with Pydantic,
  retry once with the validation error if parsing fails.
- Long transcripts: if over the model's comfortable context, summarise in chunks first (map),
  then generate metadata from the summaries plus timestamps (reduce).
- Prompts live in `ai-service/app/prompts/` as templates, never inline in code.
- Cache results by `hash(transcript + preset + custom_prompt + model)` to avoid paying twice.
- Every provider call reports usage (minutes, tokens, frames) so Laravel can write `usage_records`.

### Platform presets (keep in a config file — limits change, verify them)

| Preset      | Title max | Description/caption max | Tags/hashtags                                                               |
| ----------- | --------- | ----------------------- | --------------------------------------------------------------------------- |
| `youtube`   | 100 chars | 5000 chars              | tags ≤ 500 chars total; chapters: first at 00:00, ≥ 3 chapters, each ≥ 10 s |
| `tiktok`    | —         | ~2200 chars (verify)    | 3–5 hashtags                                                                |
| `instagram` | —         | 2200 chars              | ≤ 30 hashtags (aim for 5–10)                                                |
| `linkedin`  | —         | 3000 chars              | 3–5 hashtags, professional tone                                             |
| `generic`   | 100 chars | 2000 chars              | 10–15 tags                                                                  |

---

## 7. Python service API (internal only)

All requests in both directions are signed:
`X-Signature: sha256=HMAC(INTERNAL_SHARED_SECRET, timestamp + "." + raw_body)` and
`X-Timestamp`. Reject requests older than 5 minutes. Laravel's `VerifyInternalSignature`
middleware and Python's `security.py` implement the same scheme.

| Method | Path                    | Body (summary)                                                                             | Returns                                                |
| ------ | ----------------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------ |
| GET    | `/health`               | —                                                                                          | `{status, version, gpu: bool}`                         |
| POST   | `/jobs/process`         | `{record_id, record_file_id, video_path, options, callback_url}`                           | `{job_id}` (202)                                       |
| POST   | `/jobs/{job_id}/cancel` | —                                                                                          | `{ok}`                                                 |
| POST   | `/generate/metadata`    | `{record_id, transcript, language, preset, custom_prompt?, visual_context?, title_count}`  | `AiMetadata`                                           |
| POST   | `/generate/titles`      | `{transcript_summary, preset, count, avoid: string[]}`                                     | `{titles: string[]}`                                   |
| POST   | `/translate`            | `{segments, source_language, target_language}`                                             | `{segments}` (async job + callback for long ones)      |
| POST   | `/embed`                | `{texts: string[]}`                                                                        | `{vectors: number[][]}`                                |
| POST   | `/chat`                 | `{question, context_chunks: [{start,end,text}], history}`                                  | `{answer, citations: [{start,end}]}`                   |
| POST   | `/clips`                | `{record_id, video_path, start, end, output_path}`                                         | `{job_id}` → callback                                  |
| POST   | `/import-url`           | `{record_id, url, output_dir, callback_url}`                                               | `{job_id}` → callback with file path + source metadata |
| POST   | `/frames/capture`       | `{video_path, time_sec, output_path}`                                                      | `{path}` (custom thumbnail at a timestamp)             |
| POST   | `/jobs/ingest-file`     | `{record_id, record_file_id, path, role, expected_checksums?, callback_url}`               | `{job_id}` → callback with probe + checksums (+ proxy if needed) for an added file |
| POST   | `/jobs/fixity`          | `{record_file_id, path, expected: {md5, sha256}, callback_url}`                            | `{job_id}` → callback with actual checksums + match result |

Chat flow (keeps Python DB-free): Laravel → `/embed` (question) → Laravel queries pgvector for
top-k chunks → Laravel → `/chat` with those chunks → answer with timestamp citations.

---

## 8. Laravel app

### Data model (migrations)

All tables below already exist as migrations; later phases build features on them. Tables marked
*(added in Phase N)* are created in that phase. Records, projects, series, files, works and
attachments use ULID primary keys (they appear in URLs); everything else uses big integers.

**People, lookups and locations**

- `users` — starter kit, plus `role` (`admin|encoder|user`, default `user`), `is_active`,
  `daily_processing_minutes_limit?` (null = config default). `role` and `is_active` are never
  mass-assignable.
- `regions`, `provinces` (`region_id`), `cities` (`province_id`, cities and municipalities),
  `barangays` (`city_id`) — Philippine locations seeded by `LocationSeeder` from
  `database/data/updated_phil.sql`, keeping the original IDs. Read-only in the app.
- `production_agencies` — `name`, `acronym?`, `parent_id?`. A row with `parent_id` is a production
  unit (division) of that agency. Admin-managed.
- `genres`, `video_types` — `name`, `slug`, `sort_order`. Admin-managed lookup lists for consistent
  classification and public filters.

**Grouping**

- `projects` — event or programme: `title`, `date?`, `description?`, `location?`, `region_id?`,
  `prov_id?`, `city_id?`, `brgy_id?`, `agency_id?`, `thumbnail_path?`.
- `project_series` — sub-event inside a project: `project_id` plus the same fields as `projects`.
- `works` — `created_by`, `title`, `total_parts?`. Groups the reels/parts of one work; each part is
  a record with `work_id`, `part_number`, `part_label` (e.g. "Reel 2").

**Records**

- `records` — one archived video:
  - Ownership and grouping: `created_by` (encoder), `accession_number?` (unique),
    `project_id?`, `project_series_id?`, `work_id?`, `part_number?`, `part_label?`, `agency_id?`,
    `division_id?` (a production unit).
  - Descriptive: `title`, `alternative_title?`, `description?`, `genre_id?`, `video_type_id?`,
    `language?`, `language_confidence?`, `country_origin?`, `date_recorded?`,
    `date_recorded_precision?` (`day|month|year|circa`), `date_recorded_source?`, `date_published?`,
    `season_number?`, `episode_number?`.
  - Location: `location?` (free text), `region_id?`, `prov_id?`, `city_id?`, `brgy_id?`,
    `latitude?`, `longitude?`, `location_source?`.
  - Thumbnail: `thumbnail_path?` (uploaded image) or `thumbnail_frame_id?` (one of its frames).
  - Archival management: `security_classification` (`unclassified|restricted|confidential|secret|
    top_secret`, default `unclassified`), `retention_period?`, `archival_value?`
    (`permanent|temporary`), `disposition_date?`, `disposition_action?`
    (`retain|transfer|destroy|review`).
  - Processing: `processing_status` (`queued|processing|ready|failed|cancelled`),
    `processing_error?`.
  - Approval: `review_status` (`draft|submitted|approved|rejected|changes_requested`),
    `submitted_at?`, `reviewed_by?`, `reviewed_at?`, `published_at?`.
  - Timestamps and soft deletes. Workflow fields (accession number, processing, approval) are set
    explicitly by the system or an admin, never mass-assigned.
- `record_files` — `record_id`, `role` (`master|mezzanine|proxy|access`), `storage_location`,
  `original_filename?`, `mime_type?`, `file_size?`, `md5_checksum?`, `sha256_checksum?`,
  `expected_md5?`, `expected_sha256?`, `checksum_status` (`pending|ok|mismatch`).
- `record_technical_metadata` — one per record file (`record_file_id`): `file_format`, `duration`,
  `bitrate`, `start_timecode`; primary video stream: `video_codec`, `video_profile`, `resolution`,
  `frame_rate`, `aspect_ratio`, `scan_type` (`progressive|interlaced_tff|interlaced_bff|unknown`),
  `bit_depth`, `chroma_subsampling`, `color_space`, `color_primaries`, `color_transfer`; primary
  audio: `audio_codec`, `audio_channels`, `audio_sample_rate`; `audio_tracks (jsonb)` for every
  audio track; `raw_probe (jsonb)` with the full ffprobe + mediainfo output.
- `fixity_checks` — `record_file_id`, `algorithm`, `expected`, `actual?`, `matched?`,
  `triggered_by` (`ingest|schedule|user`), `checked_at`. **Append-only.**
- `record_stages` — `record_id`, `stage` (§6), `status` (`pending|running|done|failed|skipped`),
  `progress (0–100)`, `message?`, `started_at?`, `finished_at?`. Unique per record and stage.
- `record_metadata` — AI generations: `record_id`, `preset`, `custom_prompt?`, `model`,
  `payload (jsonb)` (shape = `ai` in §10), `is_active`. History is kept; one active per record.
- `record_contributors` — credits: `record_id`, `role` (`producer|director|videographer|editor|
  presenter|cast|contributor`), `name`, `sort_order`.
- `record_rights` — one per record: `copyright_holder?`, `copyright_year?`, `rights_statement?`,
  `license?`, `license_url?`, `access_level` (`public|internal|restricted`, default `restricted`),
  `usage_restrictions?`, `territories (jsonb)?`, `embargo_until?`, `permission_status`
  (`pending|approved|denied`), `release_consent` (`available|not_available`).
- `record_subjects` — `record_id`, `subject`, `type` (`person|organisation|place|topic|event`),
  `source` (`manual|ai|transcript|ocr|...`), `is_confirmed`.
- `record_keywords` — `record_id`, `keyword`, `source`, `is_confirmed`.
- `record_speakers` — `record_id`, `label` (S1…), `display_name?`.
- `record_transcripts` — one per language: `record_id`, `language`, `is_original` (false for
  translations), `status`.
- `record_segments` — `record_transcript_id`, `position`, `start`, `end`, `text`, `original_text`,
  `record_speaker_id?`, `is_edited`. Indexed by start time; full-text index on `text` (PostgreSQL).
- `record_frames` — `record_id`, `time_sec`, `path`, `is_scene_start`, `description?`, `ocr_text?`,
  `objects (jsonb)?`, `face_count?` (counts only).
- `record_scenes` — `record_id`, `kind` (`shot|scene`: shots are detected, scenes are user-defined
  groups of shots), `start`, `end`, `tc_in?`, `tc_out?`, `label?`, `description?`.
- `record_provenance` — one per record: `source_carrier?` (`born_digital|camera_card|digibeta|
  betacam_sp|hdcam|dvcam|minidv|umatic|vhs|film_16mm|film_35mm|other`), `carrier_label?`,
  `carrier_id?`, `transfer_date?`, `transfer_facility?`, `transfer_operator?`, `transfer_deck?`,
  `transfer_settings?`, `notes?`.
- `record_attachments` — private files: `record_id`, `uploaded_by?`, `kind` (`release_form|
  contract|sidecar|subtitle|document|other`), `path`, `original_filename`, `mime_type`,
  `file_size`, `sha256_checksum`, `description?`. Private disk only.

**Logs and usage**

- `video_audit_logs` — `record_id`, `record_file_id?`, `action` (`uploaded|updated|submitted|
  approved|changes_requested|rejected|reopened|deleted|restored|transcoded|migrated|replaced|
  checksum_verified|checksum_failed|exported`), `performed_by?`, `performed_at`, `remarks?` (review
  notes go here). **Append-only**; stores IDs without foreign keys so history survives a purge.
- `access_logs` — `record_id`, `user_id?`, `action` (`view|stream|download|export`), `ip_address?`,
  `user_agent?`, `accessed_at`. **Append-only.**
- `usage_records` — `user_id?`, `record_id?`, `kind` (`transcription_minutes|llm_input_tokens|
  llm_output_tokens|vision_frames|storage_bytes`), `quantity`, `estimated_cost?`, `occurred_at`.
  **Append-only.**

**Added in their phase**

- `export_schemas` *(Phase 2)* — `user_id`, `name`, `fields (jsonb: dot-paths)`, `is_default`.
- `batches` *(Phase 6)* — `created_by`, `name?`, `status`, `total`, `completed`, `failed`,
  `zip_path?`; records get `batch_id?`.
- `transcript_chunks` *(Phase 7)* — `record_id`, `start`, `end`, `text`,
  `embedding vector(EMBEDDING_DIMENSIONS)` with an HNSW index.
- `chat_messages` *(Phase 7)* — `record_id`, `user_id`, `role`, `content`, `citations (jsonb)`.
- `clips` *(Phase 7)* — `record_id`, `start`, `end`, `title?`, `path?`, `status`.
- `youtube_accounts`, `youtube_publications` *(Phase 7)* — connected channels (encrypted tokens) and
  publish attempts.

**Access (policies on every model):** encoders see and edit only the records they created (and only
while `review_status` is `draft` or `changes_requested`); admins see and edit everything; public
visitors see only records that pass `distribute`.

**Distribution policy:** a single check (`RecordPolicy::distribute`) decides whether a record may
leave the archive. It requires `review_status = approved`, `published_at` set, not soft-deleted,
`security_classification = unclassified`, and `record_rights->allowsPublicAccess()` (access level
`public`, permission `approved`, no embargo in force). The public catalogue, YouTube publishing,
public-API export and batch ZIPs all use it. Enforce it server-side; hiding buttons is not enough.

### Routes (web, Inertia)

Routes are split into one file per role (see AGENTS.md):

- `routes/encoder.php` — the encoder routes below; middleware `auth` + `role:encoder,admin`
  (admins pass every role check, so they get every encoder feature).
- `routes/admin.php` — `/admin/*`; middleware `auth` + `role:admin`.
- `routes/user.php` — the public catalogue and location lookups; open to guests unless
  `PUBLIC_CATALOGUE_REQUIRES_LOGIN`.
- `routes/all.php` — profile and appearance settings shared by every logged-in role.

**Encoder (and admin):**

- `GET /dashboard` — record history (search, filter by processing and review status, sort, pagination).
- `GET /records/create` — upload / paste / URL / batch, with project and series pickers.
- `POST /records` — single upload or URL. Chunked/resumable upload for big files
  (simple chunk endpoint; max size from config).
- `POST /batches` — multiple files/URLs → one batch + many records (Phase 6).
- `GET /records/{record}` — workspace.
- `PATCH /records/{record}` — descriptive, location, classification and retention fields.
- `PATCH /records/{record}/segments/{segment}` — edit text / speaker.
- `PATCH /records/{record}/speakers/{speaker}` — rename.
- `POST /records/{record}/metadata` — regenerate (preset, custom prompt).
- `POST /records/{record}/titles` — more title options.
- `POST /records/{record}/translations` — `{language}`.
- `POST /records/{record}/thumbnail` — `{time_sec}`, `{frame_id}` or an uploaded image.
- `PUT /records/{record}/contributors` — replace the credit list.
- `POST /records/{record}/subjects`, `PATCH|DELETE /records/{record}/subjects/{subject}`, and the
  same for `keywords` — add, confirm or reject (including AI suggestions).
- `PUT /records/{record}/rights`, `PUT /records/{record}/provenance`.
- `POST /records/{record}/attachments`, `GET|DELETE /records/{record}/attachments/{attachment}`
  (release forms, sidecars; downloads go through the policy, never a public URL).
- `POST /records/{record}/files` — upload an extra file (mezzanine, access copy).
- `POST /records/{record}/fixity` — run a fixity check now.
- `POST /records/{record}/chat` — ask a question (Phase 7).
- `POST /records/{record}/clips` — `{start, end, title?}`; `GET` to download (Phase 7).
- `GET /records/{record}/export?format=json|srt|vtt|txt|csv|md&schema={id?}&language={code?}`
- `POST /records/{record}/youtube` — publish (Phase 7).
- `POST /records/{record}/cancel`, `POST /records/{record}/retry?stage=`
- `POST /records/{record}/submit` — submit for approval (only from `draft` or `changes_requested`).
- `GET|POST /projects`, `GET|PATCH /projects/{project}`, `POST /projects/{project}/series`,
  `PATCH /project-series/{series}` — manage projects (events) and their series.
- `POST /works`, `GET /works/{work}`, `PATCH /works/{work}` — group records as reels/parts.
- `GET /batches/{batch}`, `GET /batches/{batch}/download` (ZIP of all exports + optional thumbnails).
- `GET /settings/export-schemas`, `GET /settings/api-tokens`, `GET /settings/youtube`.

**Admin only (`/admin`):**

- `GET /admin` — overview: pending approvals, processing queue, usage this month.
- `GET /admin/approvals` — review queue (submitted records, oldest first).
- `GET /admin/approvals/{record}` — review page; `POST /admin/approvals/{record}` —
  `{action: approve|reject|request_changes, notes?}` (notes required for reject and
  request_changes).
- `POST /admin/records/{record}/reopen` — back to `draft`, removed from the public catalogue.
- `GET /admin/records` — all records from every encoder (opens the same workspace).
- `GET /admin/users`, `POST /admin/users`, `PATCH /admin/users/{user}` — create encoders and
  admins, change role, deactivate, set the daily processing limit. Admins cannot demote or
  deactivate themselves, so there is always at least one admin.
- `GET /admin/usage` — usage per encoder and per record (minutes, tokens, storage, estimated
  cost) for a date range; CSV download.
- `GET|POST /admin/agencies`, `PATCH|DELETE /admin/agencies/{agency}` — agencies and their units.
- `GET|POST /admin/genres`, `PATCH|DELETE /admin/genres/{genre}`; the same for `/admin/video-types`.
- `GET /admin/records/{record}/access-log` — who viewed or downloaded a record.

**Public catalogue (`user` role and guests):**

- `GET /` — catalogue home: latest approved records, current and recent projects, genres.
- `GET /browse?project=&series=&genre=&type=&agency=&region=&year=&month=&q=&sort=date_desc|date_asc|title`
  — paginated grid; date means `date_recorded`, falling back to the project date, then
  `published_at`.
- `GET /projects/{project}` (public view), `GET /project-series/{series}` (public view).
- `GET /watch/{record}` — public player page: access copy, captions, chapters, transcript and the
  public fields only (§1 Roles). Each view is written to `access_logs`.
- `GET /locations/provinces?region=`, `/locations/cities?province=`, `/locations/barangays?city=` —
  cascading location pickers (cached, read-only).
- Public routes are read-only, rate-limited and cached; the cache is cleared when a record is
  approved, reopened or edited by an admin.

### Internal routes (Python → Laravel, signed, no session)

- `POST /internal/records/{record}/progress`
- `POST /internal/records/{record}/results/{stage}`
- `POST /internal/record-files/{recordFile}/results/{stage}` — probe / checksum / proxy for added files
- `POST /internal/record-files/{recordFile}/fixity` — fixity check result
- `POST /internal/clips/{clip}/done`
- `POST /internal/imports/{record}/done`

### Public API (Sanctum tokens, `/api/v1`)

Tokens can be created by encoders and admins only, and act with that user's permissions.

- `POST /records` (multipart file or `{url}`, options) → `{id, status}`
- `GET /records/{id}` → processing status + stages + review status
- `GET /records/{id}/export?format=…`
- `GET /records` (paginated)
- Optional: `webhook_url` on create; Laravel POSTs when the record is ready.
- Rate-limit per token.

### Exports

Exporters live in `app/Exporters`, all implement `Exporter::export(Record, ExportOptions): string`.

- **JSON** — the schema in §10, filtered by the chosen `export_schemas.fields` if given.
- **SRT / VTT** — from transcript segments (optionally a translation); include speaker
  prefix if speakers exist. VTT is also served to the player as a `<track>`.
- **TXT** — plain transcript, optional timestamps and speakers.
- **CSV** — one row per segment: `start,end,speaker,text`.
- **Markdown** — title, description, credits, chapters, tags, quotes, transcript, plus a rights
  summary (copyright, licence, restrictions, embargo) when one exists.

---

## 9. Frontend (Inertia + React + TypeScript)

### Pages

Pages are grouped by role folder (see AGENTS.md).

**Encoder (admins use the same pages):**

- `pages/encoder/dashboard.tsx` — record history table/grid with thumbnail, title, duration,
  processing status badge, review status badge, created date; search; status filters; "New record".
- `pages/encoder/records/create.tsx` — tabs: **Upload** (dropzone, multiple files → batch, paste from
  clipboard), **URL** (one per line). Project and series pickers. Options panel: preset, custom
  prompt, toggles for diarization / visual analysis / OCR / objects, translation languages.
- `pages/encoder/records/show.tsx` — the workspace (layout below), with a "Submit for approval"
  button and the admin's review notes; read-only for encoders once submitted or approved.
- `pages/encoder/projects/index.tsx`, `pages/encoder/projects/show.tsx` — projects (events), their
  series and the records filed under them.
- `pages/encoder/batches/show.tsx` — batch progress list + "Download ZIP".
- `pages/encoder/settings/*` — export schemas builder, API tokens, YouTube connection.

**Admin:**

- `pages/admin/overview.tsx` — pending approvals, processing queue, usage this month.
- `pages/admin/approvals/index.tsx` — review queue; `pages/admin/approvals/show.tsx` — preview the
  video and the full record, then approve / request changes / reject with notes; review history.
- `pages/admin/records.tsx` — all records, any encoder.
- `pages/admin/users/index.tsx` + `partials/` — user list, create, change role, deactivate, limits.
- `pages/admin/usage.tsx` — usage charts and table per encoder/record, date range, CSV download.
- `pages/admin/agencies.tsx`, `pages/admin/genres.tsx`, `pages/admin/video-types.tsx` — lookups.

**Public (`user` role and guests):**

- `pages/user/home.tsx` — latest records, projects, genres.
- `pages/user/browse.tsx` — grid with filters: date (year → month timeline), project, series, genre,
  video type, agency, region, search; sort by date or title.
- `pages/user/project.tsx`, `pages/user/series.tsx` — records in one project or series.
- `pages/user/watch.tsx` — player with captions, chapters, transcript and public metadata.

**All logged-in roles:**

- `pages/all/settings/*` — profile, password, appearance (starter kit).

### Workspace layout (`records/show.tsx`)

```
┌───────────────────────────────┬──────────────────────────────┐
│  VideoPlayer (+ captions)     │  Right panel (Tabs):         │
│  Timeline: scenes, chapters,  │   Metadata | Archive |       │
│  speaker colours, waveform    │   Visuals | Translations |   │
├───────────────────────────────┤   Chat | Clips | Export |    │
│                               │   Publish                    │
│  TranscriptPanel              │                              │
│  [search] [speaker filter]    │                              │
│  segments (virtualised)       │                              │
└───────────────────────────────┴──────────────────────────────┘
 PipelineProgress bar (top, while processing)
```

### Key components

- `VideoPlayer` — native `<video>` with custom controls, VTT `<track>` for captions (toggle,
  language picker), exposes a ref API (`seek`, `play`, `pause`, `currentTime`). Plays the proxy
  when the master isn't browser-playable.
- `useVideoSync` — subscribes to `timeupdate` (+ `requestAnimationFrame` while playing for
  smoothness), binary-searches the active segment, auto-scrolls the transcript (pause
  auto-scroll while the user is scrolling manually; show a "Back to current" button).
- `TranscriptPanel` / `TranscriptSegment` — click timestamp to seek; highlight active
  segment; inline edit (Enter saves, Esc cancels, debounced PATCH); speaker chip with colour.
- `CurrentLineCopy` — when the video is **paused**, show a floating card with the current
  segment text and a **Copy** button (`navigator.clipboard.writeText`), plus "Copy with
  timestamp".
- `TranscriptSearch` — highlight all matches, "3 of 12" with next/prev, click → seek.
- `SpeakerManager` — rename speakers, colour legend.
- `MetadataPanel` — preset switcher; title options (radio list + "Generate more"); editable
  description with character counter against preset limit; tags/keywords/hashtags as
  editable chip inputs; chapters list (editable, click → seek, "Copy for YouTube" button
  that produces `00:00 Intro` lines); quotes (click → seek, "Create clip"); tone + content
  flags badges; custom prompt box + "Regenerate"; generation history dropdown.
- `ArchivePanel` — sub-tabs:
  **Descriptive** (title, alternative title, description, genre, video type, language, country of
  origin, date recorded with precision, project/series, season/episode, subjects and keywords with
  confirm/reject for AI suggestions);
  **Location** (`LocationPicker`: region → province → city/municipality → barangay, free-text
  location, GPS);
  **Credits** (agency, production unit, contributors by role);
  **Technical** (read-only, per record file: format, codecs, profile, resolution, frame rate, scan
  type, bit depth, chroma, colour, audio tracks, start timecode, checksums with status);
  **Rights** (copyright, rights statement, licence, access level, restrictions, territories,
  embargo, permission, release consent, release-form uploads);
  **Provenance** (source carrier, transfer details, audit-log timeline, fixity history, "Check now");
  **Archival** (accession number, security classification, retention period, archival value,
  disposition date and action);
  **Structure** (record files, work/reel grouping, attachments, transcripts by language,
  shot/scene segments).
- `LocationPicker` — cascading selects backed by the `/locations/*` endpoints (TanStack Query,
  cached).
- `TimecodeToggle` — switch every time display between seconds and SMPTE timecode
  (`HH:MM:SS:FF`, `;` for drop-frame), using the master's start timecode and frame rate.
- `EmbargoBadge` / classification badge / restrictions notice — shown on the workspace header,
  Export and Publish panels.
- `VisualsPanel` — thumbnail picker grid (frames) + "Use current frame"; scenes timeline;
  per-frame description, OCR text, objects, face count.
- `TranslationsPanel` — add a language, view/download translated transcript and subtitles.
- `ChatPanel` — chat with the video; answers show clickable timestamp citations.
- `ClipsPanel` — select a range (from transcript selection, quote, or chapter), name it,
  export, download.
- `ExportPanel` — format picker, schema picker, language picker, live JSON preview, Download.
- `PublishPanel` — connect YouTube, choose privacy, preview title/description/tags/thumbnail,
  publish, show status/link.
- `PipelineProgress` — stage list from `record_stages` with status icons + overall progress;
  listens on Echo channel `records.{id}`; Inertia partial reload (`router.reload({ only: [...] })`)
  when a stage completes.
- `ShortcutsDialog` — opened with `?`.

### Keyboard shortcuts (disabled while typing in an input/textarea)

| Key           | Action                              |
| ------------- | ----------------------------------- |
| `Space` / `K` | Play / pause                        |
| `J` / `L`     | Back / forward 5 s                  |
| `←` / `→`     | Previous / next segment             |
| `C`           | Copy current segment                |
| `Shift+C`     | Copy current segment with timestamp |
| `/`           | Focus transcript search             |
| `E`           | Edit current segment                |
| `T`           | Set current frame as thumbnail      |
| `?`           | Show shortcuts                      |

### Frontend conventions

- Strict TypeScript; types in `resources/js/types/` mirror §10 exactly.
- Follow AGENTS.md: one component per file with sub-components in `partials/`, server calls via
  TanStack Query hooks in `resources/js/hooks/`, shared helpers in `lib/utilities.ts`.
- Zustand for workspace UI state (current time, active segment, search, selection).
- Every async action shows loading state and a toast on success/failure.
- Dark mode via the starter kit's appearance setting; all custom UI must work in both themes.
- Accessible: keyboard reachable, labelled buttons, visible focus rings.

---

## 10. Metadata JSON schema (export format)

`schemaVersion` must be bumped on breaking changes. Times are seconds (float).

```json
{
    "schemaVersion": "1.0",
    "generatedAt": "2026-01-01T12:00:00Z",
    "generator": { "app": "metadata-generator-and-archival-system", "version": "0.1.0" },
    "record": {
        "id": "01J...",
        "accessionNumber": "ACC-2026-00042",
        "title": "Regional S&T Fair opening ceremony",
        "alternativeTitle": null,
        "project": { "id": "01J...", "title": "Regional S&T Fair 2026", "date": "2026-07-10" },
        "series": { "id": "01J...", "title": "Day 1", "date": "2026-07-10" },
        "work": { "id": "01J...", "title": "...", "partNumber": 1, "partLabel": "Reel 1", "totalParts": 3 },
        "agency": { "name": "Department of Science and Technology", "acronym": "DOST" },
        "productionUnit": { "name": "Science and Technology Information Institute", "acronym": "STII" },
        "reviewStatus": "approved",
        "publishedAt": "2026-07-20T09:00:00Z"
    },
    "descriptive": {
        "description": "...",
        "genre": "Event Coverage",
        "videoType": "Full Program",
        "language": { "code": "en", "confidence": 0.98 },
        "countryOfOrigin": "PH",
        "dateRecorded": { "value": "2026-07-10", "precision": "day", "source": "manual" },
        "datePublished": null,
        "season": null,
        "episode": null,
        "location": {
            "text": "Provincial Capitol grounds",
            "region": "Region VII",
            "province": "Cebu",
            "city": "Cebu City",
            "barangay": "Capitol Site",
            "latitude": 10.3157,
            "longitude": 123.8854,
            "source": "manual"
        },
        "subjects": [{ "subject": "Jane Doe", "type": "person", "source": "transcript", "confirmed": true }],
        "keywords": [{ "keyword": "innovation", "source": "ai", "confirmed": true }]
    },
    "contributors": [
        { "role": "producer", "name": "..." },
        { "role": "director", "name": "..." }
    ],
    "technical": {
        "files": [
            {
                "id": "01J...",
                "role": "master",
                "originalFilename": "opening.mov",
                "mimeType": "video/quicktime",
                "fileSize": 48211234,
                "checksums": { "md5": "...", "sha256": "...", "status": "ok" },
                "fileFormat": "QuickTime / MOV",
                "duration": 312.5,
                "bitrate": 4500000,
                "startTimecode": "01:00:00:00",
                "video": {
                    "codec": "prores",
                    "profile": "422 HQ",
                    "resolution": "1920x1080",
                    "frameRate": "25/1",
                    "aspectRatio": "16:9",
                    "scanType": "interlaced_tff",
                    "bitDepth": 10,
                    "chromaSubsampling": "4:2:2",
                    "colorSpace": "bt709",
                    "colorPrimaries": "bt709",
                    "colorTransfer": "bt709"
                },
                "audio": {
                    "codec": "pcm_s24le",
                    "channels": 2,
                    "sampleRate": 48000,
                    "tracks": [{ "index": 1, "codec": "pcm_s24le", "channels": 2, "language": "eng" }]
                }
            }
        ]
    },
    "rights": {
        "copyrightHolder": "DOST",
        "copyrightYear": 2026,
        "rightsStatement": "Government-produced audiovisual material",
        "license": "Agency-defined license",
        "licenseUrl": null,
        "accessLevel": "public",
        "usageRestrictions": "Subject to agency approval",
        "territories": ["PH"],
        "embargoUntil": null,
        "permissionStatus": "approved",
        "releaseConsent": "available"
    },
    "archival": {
        "securityClassification": "unclassified",
        "retentionPeriod": "Permanent",
        "archivalValue": "permanent",
        "disposition": { "date": null, "action": null }
    },
    "provenance": {
        "ingestedAt": "2026-07-11T11:58:00Z",
        "sourceCarrier": { "type": "camera_card", "label": "Card A", "id": "CF-0014" },
        "transfer": {
            "date": "2026-07-11",
            "facility": "...",
            "operator": "...",
            "deck": null,
            "settings": "Direct card offload"
        },
        "auditLog": [
            { "action": "uploaded", "fileId": "01J...", "performedBy": "Encoder", "performedAt": "2026-07-11T11:58:00Z", "remarks": null }
        ],
        "fixityChecks": [
            { "fileId": "01J...", "algorithm": "sha256", "matched": true, "checkedAt": "2026-10-01T02:00:00Z" }
        ]
    },
    "structure": {
        "files": [
            { "fileId": "01J...", "role": "master" },
            { "fileId": "01J...", "role": "proxy" }
        ],
        "attachments": [{ "id": "01J...", "kind": "sidecar", "name": "tape-log.xml", "sha256": "..." }],
        "transcripts": [
            { "language": "en", "original": true },
            { "language": "fil", "original": false }
        ],
        "segments": [
            { "kind": "shot", "start": 0, "end": 18.4, "tcIn": "01:00:00:00", "tcOut": "01:00:18:10", "label": null }
        ]
    },
    "speakers": [{ "id": "S1", "name": "Host" }],
    "transcript": [
        {
            "id": 1,
            "start": 0.0,
            "end": 4.2,
            "speaker": "S1",
            "text": "Welcome to the fair...",
            "edited": false
        }
    ],
    "translations": {
        "fil": [
            { "id": 1, "start": 0.0, "end": 4.2, "text": "Maligayang pagdating..." }
        ]
    },
    "ai": {
        "preset": "youtube",
        "model": "claude-...",
        "customPrompt": null,
        "titles": ["Option A", "Option B", "Option C"],
        "selectedTitle": "Option A",
        "description": "...",
        "summary": "...",
        "tags": ["..."],
        "keywords": ["..."],
        "hashtags": ["#..."],
        "chapters": [{ "start": 0, "title": "Intro" }],
        "quotes": [
            { "start": 42.1, "end": 47.8, "speaker": "S1", "text": "..." }
        ],
        "tone": "educational",
        "contentFlags": { "profanity": false, "sensitiveTopics": [] }
    },
    "visual": {
        "thumbnail": { "timeSec": 12.3, "path": "thumbnails/01J....jpg" },
        "frames": [
            {
                "timeSec": 12.3,
                "description": "A speaker at a podium in front of a banner",
                "ocrText": "Regional S&T Fair 2026",
                "objects": [{ "label": "microphone", "confidence": 0.91 }],
                "faceCount": 1
            }
        ]
    },
    "clips": [
        {
            "start": 42.1,
            "end": 61.0,
            "title": "Best moment",
            "path": "clips/01J....mp4"
        }
    ],
    "publishing": { "youtube": { "videoId": null, "publishedAt": null } }
}
```

Custom export schemas are lists of dot-paths (e.g. `["record.title", "ai.description",
"ai.tags", "transcript"]`); the exporter keeps only those keys.

Shot/scene segments live in `structure.segments` (not under `visual`). Times are always seconds;
timecode strings (`tcIn`, `tcOut`, `startTimecode`) are included alongside for archival use.
Unconfirmed subjects and keywords are left out of public-facing exports.

---

## 11. Archival metadata

Every record carries a catalogue record in five categories. The table says what each one answers,
which fields hold it, and how the system fills it in.

| Category | Question it answers | Where it lives | How we capture it |
|---|---|---|---|
| **Descriptive** | What is this? | `records` (title, alternative title, description, genre, video type, language, country of origin, date recorded + precision, season/episode, location + region/province/city/barangay + GPS), `record_subjects`, `record_keywords`, `projects` / `project_series` | AI suggests description, subjects, keywords and language. Container tags suggest date recorded and GPS. The encoder confirms suggestions and fills in project, series, genre, video type and location. |
| **Technical** | How is it encoded? | `record_files` (format, size, checksums) and `record_technical_metadata` (codecs, profile, resolution, frame rate, aspect ratio, scan type, bit depth, chroma, colour, audio tracks, sample rate, duration, start timecode) | Automatic: the `probe` stage for every record file. Read-only in the UI. |
| **Administrative / rights** | Who owns it and how can it be used? | `record_rights`, `record_contributors`, `record_attachments` (release forms), `records` (accession number, agency, production unit, security classification, retention period, archival value, disposition) | Manual entry in the Rights, Credits and Archival tabs. Accession number auto-assigned at ingest from `ACCESSION_NUMBER_FORMAT` (editable, unique). Embargo and classification enforced server-side. |
| **Preservation / provenance** | What has happened to it? | `record_files` checksums, `fixity_checks`, `record_provenance`, `video_audit_logs` | Checksums and ingest date are automatic (`checksum` stage). Source carrier and transfer details are manual. Uploads, transcodes, migrations, replacements and checksum results are logged automatically in `video_audit_logs`. |
| **Structural** | How do the pieces relate? | `record_files` (master, mezzanine, proxy, access), `works` (reels/parts), `record_attachments` (sidecars), `record_transcripts` (subtitles by language), `record_scenes` | Files tracked per role (proxy generated automatically). Reels/parts grouped into a work. Shot segments come from the `scenes` stage with timecode in/out; scenes are user-defined groups of shots. |

### Rules

- **Suggested vs confirmed.** Anything AI, OCR, the transcript or file tags produce for descriptive
  fields is a suggestion (with a `source`) until an encoder confirms it. Only confirmed subjects and
  keywords appear publicly. Exports include the source.
- **People and subjects come from what is said or written** (transcript, OCR, user entry), never
  from faces. This keeps the no-face-recognition rule in §14.
- **Date recorded.** Container `creation_time` is often the encode or copy date, not the recording
  date. Show it as a suggestion with that warning. Store a precision (`day|month|year|circa`),
  because archival dates are often approximate.
- **Location.** Region, province, city/municipality and barangay come from the seeded Philippine
  location tables and must be consistent (the barangay belongs to the city, and so on); validate
  the chain in the FormRequest.
- **Timecode.** Read the start timecode from the container tag or the timecode (`tmcd`) stream;
  default `00:00:00:00`. Convert seconds ↔ SMPTE using the frame rate, with drop-frame (`;`
  separator) for 29.97 and 59.94 fps. Exports include both seconds and timecode.
- **Scan type, bit depth, chroma subsampling.** Derive from ffprobe (`field_order`,
  `bits_per_raw_sample`, `pix_fmt`), fall back to mediainfo, and store `unknown` rather than guess.
- **Checksums.** Compute MD5 and SHA-256 in one streaming pass right after the file is stored,
  before any other stage touches it. The encoder may paste an expected checksum from a source
  manifest; a mismatch sets `checksum_status = mismatch`, flags the record and logs
  `checksum_failed`. Every derived file (proxy, clip, added file) gets its own checksums.
- **Fixity checks.** A scheduled job re-hashes every master and mezzanine file every
  `FIXITY_CHECK_INTERVAL_DAYS`, and the Provenance tab has a "Check now" button. Each result is
  stored in `fixity_checks` and logged in `video_audit_logs`; a mismatch flags the record and
  notifies the owner and admins.
- **Logs are append-only.** `video_audit_logs`, `access_logs`, `fixity_checks` and `usage_records`
  have no update or delete paths, in code or in the UI.
- **Security classification.** Only `unclassified` records can ever be public. Records with any
  other classification are visible only to their creator and admins, and are excluded from public
  exports, batch ZIPs and the public API.
- **Embargo and restrictions.** While `embargo_until` is in the future, the public catalogue,
  YouTube publishing, public-API export and batch ZIPs exclude the record (the `distribute`
  policy, §8). The workspace, Export and Publish panels show the embargo and restriction text, and
  exports include them.
- **Retention and disposition.** `disposition_date` and `disposition_action` record what happens when
  the retention period ends. A scheduled job lists records whose disposition date has passed for
  admin review; nothing is destroyed automatically.
- **Release forms contain personal data.** Private disk, policy-checked downloads only, never in
  public-API responses or batch ZIPs.
- **Master and mezzanine files are never deleted automatically.** Cleanup jobs only remove
  temporary files (extracted audio, frames, old proxies that can be regenerated).
- **Standards alignment.** Field names follow PBCore / EBUCore (descriptive, technical) and
  PREMIS (events, fixity) closely enough that standards-based XML exports can be added later.
  These exports are not in scope yet; log them in `DECISIONS.md` under "Later".

---

## 12. Environment variables

### `.env` (additions)

```
APP_NAME="Metadata Generator and Archival System"
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=media              # define a "media" disk pointing at the shared volume / S3

AI_SERVICE_URL=http://ai-api:8000
INTERNAL_SHARED_SECRET=change-me   # same value in ai-service
APP_INTERNAL_URL=http://web        # URL the Python service uses for callbacks

MAX_UPLOAD_MB=2048
MAX_VIDEO_MINUTES=180
MAX_BATCH_SIZE=20
EMBEDDING_DIMENSIONS=1536

ACCESSION_NUMBER_FORMAT="ACC-{year}-{seq:5}"   # tokens: {year}, {seq:N} (zero-padded, system-wide)
FIXITY_CHECK_INTERVAL_DAYS=90

PUBLIC_CATALOGUE_REQUIRES_LOGIN=false
ALLOW_PUBLIC_REGISTRATION=true                 # sign-up creates `user` accounts only
ENCODER_DAILY_PROCESSING_MINUTES=120           # default; admins can override per encoder

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

These are read through `config/archive.php` (never `env()` outside config files).

### `ai-service/.env`

```
INTERNAL_SHARED_SECRET=change-me
LARAVEL_CALLBACK_BASE=http://web
STORAGE_ROOT=/data/storage
REDIS_URL=redis://redis:6379/1

TRANSCRIPTION_PROVIDER=openai      # openai | local | assemblyai
LOCAL_WHISPER_MODEL=small
LLM_PROVIDER=anthropic             # anthropic | openai
LLM_MODEL=                         # set to a current model id
VISION_MODEL=                      # vision-capable model id
EMBEDDING_PROVIDER=openai
EMBEDDING_MODEL=text-embedding-3-small

OPENAI_API_KEY=
ANTHROPIC_API_KEY=
ASSEMBLYAI_API_KEY=
HF_TOKEN=                          # pyannote

FRAME_INTERVAL_SEC=10
MAX_FRAMES=60
PROXY_MAX_HEIGHT=1080
```

Never expose API keys to the frontend. Never commit `.env` files.

---

## 13. Build phases

Each phase must end with: migrations run cleanly, tests pass, `README.md` updated, and a short
summary of what to try manually. The schema in §8 already exists; each phase builds features on it
(and creates the few tables marked *added in Phase N*).

### Phase 0 — Scaffold

- Docker Compose with all services (PostgreSQL with pgvector), Python FastAPI + Celery skeleton,
  `/health` endpoint, HMAC signing on both sides with tests, Reverb + Echo wired up with a test
  event, Horizon dashboard reachable (admins only). Migrations and `LocationSeeder` run against
  PostgreSQL.
- **Done when:** `docker compose up` brings everything up, migrations and seeders run on PostgreSQL,
  Laravel can call `/health`, Python can hit a signed Laravel test callback, and a broadcast event
  shows in the browser.

### Phase 1 — Core app

- Roles: `role` middleware, route files per role (`admin.php`, `encoder.php`, `user.php`,
  `all.php`), admin user management (create encoders and admins, change role, deactivate).
  Encoders see only their own records; admins see all.
- Upload (dropzone + paste) creating a `record` and its master `record_file`.
- Pipeline stages: `probe` (`record_technical_metadata`), `checksum` (MD5 + SHA-256, logged in
  `video_audit_logs`), `extract_audio`, `transcribe` (OpenAI, with chunking for > 25 MB), language
  detection, `generate_metadata` (generic + youtube presets; titles, description, summary, tags,
  keywords, hashtags, chapters). Progress in `record_stages`; `usage_records` written for every
  transcription and LLM call.
- If the uploaded master can't play in a browser (e.g. ProRes, MXF), the workspace shows a notice;
  the proxy arrives in Phase 3.
- Workspace: VideoPlayer, synced TranscriptPanel, click-to-seek, **pause → copy current line**,
  transcript search, inline editing, MetadataPanel (edit fields, regenerate), PipelineProgress
  over Reverb.
- Dashboard history list.
- JSON export.
- **Done when:** upload a 5-minute video → see live progress per stage → transcript syncs with
  playback → pause and copy a line → edit a segment → regenerate metadata → download valid JSON
  whose `technical` block lists the file's codecs and both checksums (checksums match a manually
  computed SHA-256). An admin creates an encoder account, and that encoder cannot open another
  encoder's record.

### Phase 2 — More AI output and export formats

- All platform presets, multiple title options + "generate more", custom prompt, tone +
  content flags, key quotes.
- Translations (async, per language) as extra `record_transcripts`.
- Exporters: SRT, VTT, TXT, CSV, Markdown; custom export schemas (`export_schemas` table, settings
  page + picker).
- Captions in the player (VTT track, language switcher).
- Keyboard shortcuts + shortcuts dialog. Dark mode check across all new UI.
- **Done when:** switch to TikTok preset and regenerate, translate to another language, show
  captions in that language, export SRT/VTT/CSV/MD, export JSON with a custom schema.

### Phase 3 — Archival metadata

- Projects and series (create, edit, file records under them); works (reels/parts).
- ArchivePanel tabs: Descriptive (genre, video type, date precision, season/episode, subjects and
  keywords with confirm/reject), Location (`LocationPicker` + GPS), Credits (agency, unit,
  contributors), Technical, Rights (incl. embargo, territories, licence URL), Provenance, Archival
  (classification, retention, disposition), Structure.
- `proxy` stage for masters that can't play in a browser; upload extra files (mezzanine, access),
  each probed and checksummed via `/jobs/ingest-file`.
- `generate_metadata` suggests subjects, keywords and location; container tags suggest date
  recorded and GPS. Suggestions need confirmation.
- Accession numbers, release-form and sidecar uploads (`record_attachments`, private disk),
  scheduled + manual fixity checks with notification on mismatch, disposition review list.
- `RecordPolicy::distribute` (classification + rights + embargo + approval) with tests. Later
  phases' public catalogue, publish, API export and batch ZIP must call it.
- TimecodeToggle, EmbargoBadge, classification badge; dashboard search by accession number and
  filters for embargoed and classified records.
- JSON export includes all archival blocks; Markdown export includes credits and the rights summary.
- **Done when:** upload a ProRes or MXF master → it plays through the proxy; the Technical tab
  shows scan type, bit depth, chroma, colour and audio tracks for master and proxy; confirm an AI
  subject suggestion; pick a barangay through the location picker; fill rights with an embargo and
  see the badge (policy covered by tests); record a camera-card transfer; run "Check now" and see the
  result in fixity history; group two reels into a work; file the record under a project and series;
  export JSON containing every archival block.

### Phase 4 — Approvals, usage and public catalogue

- Approval workflow on `records.review_status`, logged with notes in `video_audit_logs`: "Submit for
  approval", admin review queue with video preview and notes, request changes / reject / approve,
  reopen. Submitted and approved records are read-only for encoders.
- Admin CRUD for agencies and units, genres and video types.
- Admin overview and usage dashboard (per encoder / record, date range, CSV download). Daily
  processing limits per encoder, checked before processing starts.
- Public catalogue in `routes/user.php`: home, browse (date timeline, project, series, genre, video
  type, agency, region, search, sort), project and series pages, watch page using the access copy.
  Only records passing `distribute`; public responses expose only the public fields; views written
  to `access_logs`; cached and rate-limited.
- Public sign-up creates `user` accounts only (when `ALLOW_PUBLIC_REGISTRATION=true`).
- **Done when:** an encoder submits a record → the admin requests changes with a note → the
  encoder fixes it and resubmits → the admin approves → the video appears on the public site under
  its project, series and date, plays from the proxy, and shows no internal fields; reopening it
  removes it from the public site; an embargoed or classified approved record stays hidden; an
  encoder over the daily limit cannot start processing; the usage page shows that encoder's minutes
  and estimated cost; the access log shows the public view.

### Phase 5 — Visual analysis

- Stages: `scenes`, `frames`, `vision`, `ocr`, `objects` (store face **counts** only — no face
  recognition or identity matching) into `record_frames` and `record_scenes`.
- Users can group shots into named scenes.
- VisualsPanel: thumbnail picker, "use current frame", scenes on the timeline, frame details.
- Feed visual context into metadata generation when enabled.
- **Done when:** a slide-based video yields OCR text, frame descriptions, scene markers with
  timecodes, a chosen thumbnail, and metadata that mentions on-screen content.

### Phase 6 — Workflow

- Batch upload + batch page + ZIP download (`batches` table; records excluded by `distribute` are
  left out).
- URL import via yt-dlp (show a notice: only import videos you have the rights to).
- Speaker diarization (pyannote or AssemblyAI) into `record_speakers` + speaker rename + colours.
- Local transcription provider (faster-whisper) behind the same interface.
- Record history polish: search, filters, delete, retry failed stage, cancel.
- **Done when:** upload 3 videos as a batch, watch them process, download one ZIP; import from a
  URL; see and rename speakers.

### Phase 7 — Advanced

- Clip export (`clips` table; from transcript selection, quote, or chapter); each clip is probed and
  checksummed.
- Chat with the video (`transcript_chunks` + `chat_messages`, embed stage, pgvector search, cited
  answers).
- Public API with Sanctum tokens + settings page + rate limits + optional webhook. Export
  respects the distribution policy; release forms are never returned.
- YouTube (`youtube_accounts`, `youtube_publications`): OAuth connect, publish with
  title/description/tags/thumbnail/privacy, status tracking. Publishing respects the distribution
  policy.
- **Done when:** cut and download a clip, ask "what did they say about X?" and click the cited
  timestamp, create a record through the API with a token, publish a test video as private.

---

## 14. Non-functional requirements

- **Cost controls:** max file size, max duration, max batch size, per-encoder daily limits (config,
  overridable per user). Show an estimated cost/usage line per record. Cache LLM outputs.
- **Reliability:** every stage is idempotent and retryable; Celery tasks have time limits and
  retries with backoff (tenacity for provider calls); failed stages can be retried individually.
- **Cleanup:** scheduled job deletes temp audio/frames for records older than N days (config);
  soft-deleted records purge files after N days. Master and mezzanine files are never removed by
  the temp-file cleanup, and every purge is logged as `deleted` in `video_audit_logs`.
- **Archival integrity:** checksums at ingest for every file, scheduled fixity checks,
  append-only logs, classification and embargo enforced server-side (§11).
- **Security:** policies on every record route; signed internal callbacks; validate MIME type
  and size on upload; sanitise filenames; no API keys in the browser; rate-limit public API and
  AI-triggering endpoints; workflow fields never mass-assignable (see AGENTS.md).
- **Privacy:** no face recognition; store face counts only. Make it clear in the UI that video
  content is sent to third-party AI providers when hosted providers are selected. Access logs keep
  IP addresses for audit only and are visible to admins only.
- **Observability:** structured logs in both services with `record_id` on every line; Horizon
  for Laravel jobs; Flower optional for Celery.
- **Testing:** PHPUnit feature tests for routes, policies, exporters, internal callbacks
  (signature verification); pytest for each pipeline stage with small fixture media files and
  mocked providers; at least one end-to-end happy path per phase.
- **Code quality:** Pint, PHPStan (level 7), ESLint/Prettier, ruff, mypy all pass. No `any` in
  TypeScript without a comment explaining why.

---

## 15. Working agreement for the agent

1. Read this whole file and AGENTS.md before writing code.
2. Work one phase at a time. Use small, focused commits with clear messages.
3. Keep the TypeScript types, Pydantic schemas, and §10 JSON schema in sync — if you change one,
   change all three.
4. Prefer official docs over memory for package APIs (Laravel, Inertia, Reverb, OpenAI,
   Anthropic, PySceneDetect, etc.). Log any deviation from this spec in `DECISIONS.md`.
5. Don't add features that aren't in the current phase. Put ideas in `DECISIONS.md` under
   "Later".
6. Never run `php artisan migrate:fresh` or any command that wipes the database (AGENTS.md).
7. At the end of each phase: run all tests and linters, update `README.md`, then stop and report.
