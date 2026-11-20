# Video Metadata Extractor — Project Spec

> **For the AI coding agent reading this:** This file is the source of truth for the project.
> Build it **phase by phase** (see [Build Phases](#build-phases)). At the end of each phase, stop,
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

Core user flow:

1. User uploads a video (drag-and-drop, paste, or URL import) — one or many (batch).
2. Backend stores the file, computes checksums, and captures full technical metadata.
3. Backend extracts audio, transcribes with timestamps, detects language, labels speakers.
4. Backend analyses frames: scenes, thumbnails, frame descriptions, on-screen text, objects.
5. AI generates title options, description, tags, keywords, hashtags, chapters, quotes, tone/flags,
   and suggests descriptive fields (subjects, location).
6. User reviews everything in a workspace: video player synced with transcript, editable fields.
7. User completes the archival record: confirms descriptive fields, enters rights and provenance,
   links versions, reels and sidecar files.
8. User can pause the video and copy the current transcript line, search, edit, translate,
   chat with the video, cut clips.
9. User exports metadata (JSON by default) or publishes to YouTube.
10. Encoder submits the record for approval; once an admin approves it, it appears in the public
    catalogue.

### Roles

| Role | Who | Can do |
|---|---|---|
| `admin` | System managers | Everything an encoder can do, on **every** project, plus: manage users (create encoders and admins, change roles, deactivate), review and approve or reject submitted content, view usage and set limits, manage categories and events. |
| `encoder` | Staff who add content | Upload and process videos, edit transcripts and metadata, complete the archival record, submit the project for approval. Sees and edits only their own projects. |
| `user` | Public viewers | Browse, search and watch approved content in the public catalogue, organised by date, event, category and series. No access to the workspace or to internal metadata. |

- Guests (not logged in) can also browse the public catalogue unless
  `PUBLIC_CATALOGUE_REQUIRES_LOGIN=true`. Public sign-up creates `user` accounts only; encoders and
  admins are created by an admin.
- **Approval workflow:** `draft` → encoder submits → `submitted` → admin chooses `approved`,
  `changes_requested` or `rejected` (with notes). `changes_requested` goes back to the encoder as
  editable. Only approved content appears publicly. Approved records are read-only for encoders; an
  admin can edit them, or reopen them as `draft`, which removes them from the public catalogue
  until they are approved again. Every review action is logged in `project_reviews`.
- **The public view shows only:** the access copy (proxy, or the master if it is browser-playable;
  never a master download), title, description, date, event, category, series, confirmed subjects,
  keywords, chapters, transcript/captions, licence, credits and usage restrictions. It never shows
  rights notes, release forms, provenance, checksums, unconfirmed AI suggestions or usage data.

---

## 2. Tech stack

| Layer              | Choice                                                                                                                     |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| Main app           | **Laravel** (latest stable) using the official **React starter kit** (Inertia + React + TypeScript + Tailwind + shadcn/ui) |
| Frontend           | React + TypeScript via **Inertia.js**, Tailwind CSS, shadcn/ui, lucide-react                                               |
| Realtime           | **Laravel Reverb** (WebSockets) + **Laravel Echo** on the frontend                                                         |
| Laravel queues     | **Redis** + **Laravel Horizon**                                                                                            |
| Database           | **PostgreSQL** with the **pgvector** extension                                                                             |
| AI / media service | **Python 3.12+**, **FastAPI**, **Celery** (Redis broker)                                                                   |
| Media tools        | **ffmpeg / ffprobe** (installed in the Python container)                                                                   |
| File storage       | Shared volume in dev; S3-compatible (S3 / Cloudflare R2 / MinIO) in prod                                                   |
| Auth               | Starter kit auth (web) + **Laravel Sanctum** (public API tokens)                                                           |
| Local dev          | **Docker Compose**                                                                                                         |
| Tests              | Pest (Laravel), pytest (Python), Vitest (optional, frontend)                                                               |
| Lint/format        | Laravel Pint, ESLint + Prettier, ruff + mypy                                                                               |

### Responsibility split

- **Laravel = the product.** Users, auth, projects, database, uploads, storage, UI, queues,
  realtime events, exports, public API, YouTube OAuth/publishing.
- **Python = the brain.** Stateless worker service for anything media- or AI-heavy: probing,
  audio extraction, transcription, diarization, translation, frame/scene analysis, OCR, object
  detection, embeddings, LLM calls, clip cutting, URL downloads.
- **Python never touches the database.** It receives inputs over HTTP, reads/writes files in
  shared storage, and reports progress/results back to Laravel via signed callbacks.

---

## 3. Repository layout (monorepo)

```
video-metadata-extractor/
├── PROJECT_SPEC.md          # this file
├── DECISIONS.md             # agent logs deviations/choices here
├── README.md                # setup + run instructions
├── docker-compose.yml
├── .env.example             # shared/compose-level vars
├── web/                     # Laravel + Inertia + React + TS
│   ├── app/
│   │   ├── Actions/
│   │   ├── Enums/           # ProjectStatus, PipelineStage, Preset, ExportFormat...
│   │   ├── Events/          # ProjectProgressUpdated, ProjectStageCompleted, BatchProgressUpdated
│   │   ├── Exporters/       # JsonExporter, SrtExporter, VttExporter, TxtExporter, CsvExporter, MarkdownExporter
│   │   ├── Http/
│   │   │   ├── Controllers/{Admin,Encoder,User,All}/   # one folder per role (see AGENTS.md)
│   │   │   ├── Requests/{Admin,Encoder,User,All}/      # FormRequests, mirroring Controllers/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Controllers/Internal/   # callbacks from Python
│   │   │   └── Middleware/VerifyInternalSignature.php
│   │   ├── Jobs/            # StartProcessingJob, GenerateMetadataJob, TranslateJob, CutClipJob, BuildBatchZipJob...
│   │   ├── Models/
│   │   └── Services/AiService/AiServiceClient.php   # typed HTTP client for the Python service
│   ├── resources/js/
│   │   ├── pages/           # Inertia pages: admin/, encoder/, user/ (public), all/
│   │   ├── components/      # feature components (see §9), same role folders + partials/
│   │   ├── hooks/           # useVideoSync, useKeyboardShortcuts, useProjectChannel...
│   │   ├── stores/          # zustand stores
│   │   ├── types/           # shared TS types (mirror the JSON schema in §10)
│   │   └── lib/
│   └── tests/
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

### Laravel (`web/`)

```bash
laravel new web            # choose: React starter kit, Pest, PostgreSQL
cd web
composer require laravel/reverb laravel/horizon laravel/sanctum
composer require pgvector/pgvector            # pgvector support for Eloquent
composer require google/apiclient             # YouTube Data API (Phase 7)
composer require league/flysystem-aws-s3-v3   # S3/R2 storage (prod)
php artisan install:broadcasting              # sets up Reverb + Echo
php artisan horizon:install
php artisan install:api                       # Sanctum + routes/api.php
```

Frontend (inside `web/`):

```bash
npm install laravel-echo pusher-js            # Reverb client
npm install zustand
npm install react-dropzone
npm install @tanstack/react-virtual           # virtualised long transcripts
npm install wavesurfer.js                     # optional waveform/timeline
npm install sonner                            # toasts (if not already in starter kit)
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
| `redis`     | Redis (Laravel queues = db 0, Celery broker = db 1, Celery results = db 2) |
| `ai-api`    | `uvicorn app.main:app` (FastAPI)                                           |
| `ai-worker` | `celery -A app.worker worker`                                              |
| `minio`     | Optional S3-compatible storage for prod-like dev                           |

- `web`, `horizon`, `ai-api`, `ai-worker` all mount the **same storage volume** at the same
  path (e.g. `/data/storage`) so file paths can be passed between services.
- `ai-worker` should support an optional GPU profile (`deploy.resources.reservations.devices`)
  for local Whisper / pyannote / YOLO.

---

## 6. Processing pipeline

Laravel creates a `Project`, stores the file, then dispatches `StartProcessingJob`, which calls
`POST /jobs/process` on the Python service. Python runs a Celery chain of stages. Each stage:

1. Posts progress to Laravel: `POST {LARAVEL_URL}/internal/projects/{id}/progress`
2. Posts its result when done: `POST {LARAVEL_URL}/internal/projects/{id}/results/{stage}`
3. On failure, posts `status=failed` with an error message; later stages that depend on it are
   skipped, independent stages continue.

Laravel stores results and broadcasts `ProjectProgressUpdated` / `ProjectStageCompleted` on the
private channel `projects.{id}` via Reverb.

| #   | Stage               | What it does                                                    | Depends on                           | Phase |
| --- | ------------------- | --------------------------------------------------------------- | ------------------------------------ | ----- |
| 1   | `probe`             | ffprobe + mediainfo → full technical metadata per file and per stream (§11), plus container tags (creation time, GPS location, start timecode) | —                                    | 1     |
| 2   | `checksum`          | MD5 + SHA-256 of the stored file in one streaming pass; compare with an expected checksum if the user supplied one | —                                    | 1     |
| 3   | `extract_audio`     | ffmpeg → mono 16 kHz MP3 (~48 kbps)                             | probe                                | 1     |
| 4   | `transcribe`        | Segments with timestamps + detected language                    | extract_audio                        | 1     |
| 5   | `diarize`           | Speaker labels per segment (S1, S2…)                            | transcribe                           | 6     |
| 6   | `proxy`             | ffmpeg → browser-playable access copy (H.264/AAC MP4, ≤ `PROXY_MAX_HEIGHT`) when the master can't play in a browser; probed and checksummed as its own file | probe                                | 3     |
| 7   | `scenes`            | Shot-change timestamps (PySceneDetect), stored with timecode in/out | probe                            | 5     |
| 8   | `frames`            | Extract key frames (scene starts + every N seconds), thumbnails | probe, scenes                        | 5     |
| 9   | `vision`            | Frame descriptions via vision LLM                               | frames                               | 5     |
| 10  | `ocr`               | On-screen text per frame (EasyOCR)                              | frames                               | 5     |
| 11  | `objects`           | Object labels + face **count** per frame (YOLO)                 | frames                               | 5     |
| 12  | `embed`             | Chunk transcript (~30–60 s windows) → embeddings                | transcribe                           | 7     |
| 13  | `generate_metadata` | LLM → titles, description, tags, chapters, etc.; from Phase 3 also suggested subjects and location | transcribe (+ vision/ocr if enabled) | 1     |

`probe` and `checksum` also run on every additional file version (mezzanine, proxy, clip), so each
file has its own technical record and checksums.

Pipeline options (chosen on upload, stored on the project):
`diarization`, `visual_analysis`, `ocr`, `object_detection`, `translate_to: string[]`,
`preset`, `custom_prompt`.

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
| POST   | `/jobs/process`         | `{project_id, video_path, options, callback_url}`                                          | `{job_id}` (202)                                       |
| POST   | `/jobs/{job_id}/cancel` | —                                                                                          | `{ok}`                                                 |
| POST   | `/generate/metadata`    | `{project_id, transcript, language, preset, custom_prompt?, visual_context?, title_count}` | `AiMetadata`                                           |
| POST   | `/generate/titles`      | `{transcript_summary, preset, count, avoid: string[]}`                                     | `{titles: string[]}`                                   |
| POST   | `/translate`            | `{segments, source_language, target_language}`                                             | `{segments}` (async job + callback for long ones)      |
| POST   | `/embed`                | `{texts: string[]}`                                                                        | `{vectors: number[][]}`                                |
| POST   | `/chat`                 | `{question, context_chunks: [{start,end,text}], history}`                                  | `{answer, citations: [{start,end}]}`                   |
| POST   | `/clips`                | `{project_id, video_path, start, end, output_path}`                                        | `{job_id}` → callback                                  |
| POST   | `/import-url`           | `{project_id, url, output_dir, callback_url}`                                              | `{job_id}` → callback with file path + source metadata |
| POST   | `/frames/capture`       | `{video_path, time_sec, output_path}`                                                      | `{path}` (custom thumbnail at a timestamp)             |
| POST   | `/jobs/ingest-file`     | `{project_id, media_file_id, path, role, expected_checksums?, callback_url}`               | `{job_id}` → callback with probe + checksums (+ proxy if needed) for an added version |
| POST   | `/jobs/fixity`          | `{media_file_id, path, expected: {md5, sha256}, callback_url}`                             | `{job_id}` → callback with actual checksums + match result |

Chat flow (keeps Python DB-free): Laravel → `/embed` (question) → Laravel queries pgvector for
top-k chunks → Laravel → `/chat` with those chunks → answer with timestamp citations.

---

## 8. Laravel app

### Data model (migrations)

- `users` — from starter kit, plus `role` (`admin|encoder|user`, default `user`), `is_active`,
  `daily_processing_minutes_limit?` (null = config default).
- `projects` — `id (ulid)`, `user_id` (the encoder who owns it), `batch_id?`, `work_id?`,
  `part_number?`, `part_label?`, `category_id?`, `catalogue_event_id?`, `review_status`
  (`draft|submitted|approved|rejected|changes_requested`), `submitted_at?`, `reviewed_by?`,
  `reviewed_at?`, `published_at?`,
  `accession_number?` (unique per user), `title`, `status` (enum: `queued`,
  `processing`, `ready`, `failed`, `cancelled`), `source_type` (`upload|url`), `source_url?`,
  `original_filename`, `storage_path`, `audio_path?`, `mime_type`, `size_bytes`,
  `duration_sec?`, `width?`, `height?`, `fps?`, `video_codec?`, `audio_codec?`, `bitrate?`,
  `language?`, `language_confidence?`, `options (jsonb)`, `selected_title?`,
  `thumbnail_frame_id?`, `checksum_status` (`pending|ok|mismatch`), `error?`, timestamps, soft deletes.
  The file/technical columns are a summary of the master file for fast listing; the full technical
  record lives in `media_files` / `media_streams`.
- `project_stages` — `project_id`, `stage` (enum), `status` (`pending|running|done|failed|skipped`),
  `progress (0–100)`, `message?`, `started_at?`, `finished_at?`.
- `speakers` — `project_id`, `label` (S1…), `display_name?`.
- `transcript_segments` — `project_id`, `position`, `start`, `end`, `text`, `original_text`,
  `speaker_id?`, `is_edited`. Index on `(project_id, start)`. Full-text index on `text`.
- `translations` — `project_id`, `language`, `segments (jsonb)`, `status`.
- `metadata_generations` — `project_id`, `preset`, `custom_prompt?`, `model`, `payload (jsonb)`
  (shape = `ai` in §10), `is_active`. Keep history; one active per project.
- `scenes` — `project_id`, `kind` (`shot|scene`: shots are auto-detected, scenes are user-defined
  groups of shots), `start`, `end`, `tc_in`, `tc_out`, `label?`, `description?`.
- `frames` — `project_id`, `time_sec`, `path`, `is_scene_start`, `description?`, `ocr_text?`,
  `objects (jsonb)?`, `face_count?`.
- `transcript_chunks` — `project_id`, `start`, `end`, `text`, `embedding vector(1536)`
  (dimension from config; match the embedding model).
- `chat_messages` — `project_id`, `user_id`, `role`, `content`, `citations (jsonb)`.
- `clips` — `project_id`, `start`, `end`, `title?`, `path?`, `status`.
- `batches` — `user_id`, `name?`, `status`, `total`, `completed`, `failed`, `zip_path?`.
- `export_schemas` — `user_id`, `name`, `fields (jsonb: string[] of dot-paths)`, `is_default`.
- `youtube_accounts` — `user_id`, `channel_id`, `channel_title`, encrypted tokens.
- `youtube_publications` — `project_id`, `youtube_video_id?`, `privacy`, `status`, `error?`.

Archival tables (§11):

- `media_files` — `project_id`, `role` (`master|mezzanine|proxy|access`), `path`,
  `original_filename`, `mime_type`, `size_bytes`, `container`, `container_long?`, `duration_sec?`,
  `bitrate?`, `start_timecode?`, `md5`, `sha256`, `expected_md5?`, `expected_sha256?`,
  `checksum_status` (`pending|ok|mismatch`), `raw_probe (jsonb)` (full ffprobe + mediainfo output),
  timestamps. Index on `(project_id, role)`.
- `media_streams` — `media_file_id`, `index`, `type` (`video|audio|subtitle|data`), `codec`,
  `codec_long?`, `profile?`, `bit_rate?`, `language?`; video: `width`, `height`,
  `display_aspect_ratio`, `frame_rate` (rational string, e.g. `30000/1001`), `scan_type`
  (`progressive|interlaced_tff|interlaced_bff|unknown`), `bit_depth`, `pix_fmt`,
  `chroma_subsampling` (`4:2:0|4:2:2|4:4:4|unknown`), `color_space`, `color_primaries`,
  `color_transfer`, `color_range`; audio: `channels`, `channel_layout`, `sample_rate`, `bit_depth`.
- `catalogue_records` — `project_id` (unique), `description?`, `date_shot?`,
  `date_shot_precision` (`day|month|year|circa`), `date_shot_source`, `location_name?`,
  `location_lat?`, `location_lng?`, `location_source?`, `series_title?`, `season_number?`,
  `episode_number?`, `episode_title?`, `keywords (jsonb)`.
- `subjects` — `project_id`, `name`, `type` (`person|organisation|place|topic|event`), `source`
  (`manual|ai|transcript|ocr`), `is_confirmed`.
- `rights_records` — `project_id` (unique), `copyright_holder?`, `copyright_year?`, `licence`
  (enum: `all_rights_reserved|cc_by|cc_by_sa|cc_by_nc|cc_by_nc_sa|cc_by_nd|cc_by_nc_nd|cc0|public_domain|custom`),
  `licence_text?`, `licence_url?`, `restrictions?`, `territories (jsonb)?`, `embargo_until?`,
  `credits (jsonb: [{role, name}])`, `notes?`.
- `attachments` — `project_id`, `kind` (`release_form|contract|sidecar|subtitle|document|other`),
  `path`, `original_filename`, `mime_type`, `size_bytes`, `sha256`, `description?`. Private disk only.
- `provenance_records` — `project_id` (unique), `source_carrier` (enum: `born_digital|camera_card|
  digibeta|betacam_sp|hdcam|dvcam|minidv|umatic|vhs|film_16mm|film_35mm|other`), `carrier_label?`,
  `carrier_id?`, `transfer_date?`, `transfer_facility?`, `transfer_operator?`, `transfer_deck?`,
  `transfer_settings?`, `notes?`.
- `preservation_events` — `project_id`, `media_file_id?`, `type` (`ingest|checksum|fixity_check|
  transcode|migration|replacement|metadata_change|deletion`), `outcome` (`success|failure|warning`),
  `detail`, `agent_type` (`user|system|software`), `agent` (user id, or software + version such as
  `ffmpeg 7.1`), `occurred_at`. **Append-only**: never updated or deleted.
- `fixity_checks` — `media_file_id`, `algorithm`, `expected`, `actual`, `matched`, `checked_at`,
  `triggered_by` (`ingest|schedule|user`).
- `works` — `user_id`, `title`, `total_parts?`. Groups multi-reel / multi-part projects
  (`projects.work_id`, `part_number`, `part_label` such as "Reel 2").

Roles, approvals and catalogue tables:

- `categories` — `name`, `slug` (unique), `description?`, `parent_id?` (one level of nesting),
  `sort_order`. Admin-managed.
- `catalogue_events` — `name`, `slug` (unique), `starts_on`, `ends_on?`, `location?`,
  `description?`. Admin-managed. (Named `CatalogueEvent` to avoid clashing with Laravel's event
  system.)
- `project_reviews` — `project_id`, `actor_id`, `action`
  (`submitted|approved|rejected|changes_requested|reopened`), `notes?`, `created_at`.
  **Append-only.**
- `usage_records` — `user_id`, `project_id?`, `kind` (`transcription_minutes|llm_input_tokens|
  llm_output_tokens|vision_frames|storage_bytes`), `quantity`, `estimated_cost?`, `occurred_at`.
  Index on `(user_id, occurred_at)`.

Index `projects` on `(review_status, published_at)`, `category_id` and `catalogue_event_id` for
the public catalogue.

**Access (policies on every model):** encoders see and edit only their own projects; admins see and
edit all of them; public visitors see only projects that pass `distribute`. Use ULIDs for public IDs.

**Distribution policy:** a single policy check (`ProjectPolicy::distribute`) decides whether a
project may leave the archive. It requires `review_status = approved`, `published_at` set, not
soft-deleted, and `rights_records.embargo_until` empty or in the past. The public catalogue, YouTube
publishing, public-API export and batch ZIPs all use it. Enforce it server-side; hiding buttons is
not enough.

### Routes (web, Inertia)

Routes are split into one file per role (see AGENTS.md):

- `routes/encoder.php` — the encoder routes below; middleware `auth` + `role:encoder,admin`
  (admins pass every role check, so they get every encoder feature).
- `routes/admin.php` — `/admin/*`; middleware `auth` + `role:admin`.
- `routes/user.php` — the public catalogue; open to guests unless `PUBLIC_CATALOGUE_REQUIRES_LOGIN`.
- `routes/all.php` — profile and appearance settings shared by every logged-in role.

**Encoder (and admin):**

- `GET /dashboard` — project history (search, filter by status, sort, pagination).
- `GET /projects/create` — upload / paste / URL / batch.
- `POST /projects` — single upload or URL. Chunked/resumable upload for big files
  (simple chunk endpoint; max size from config).
- `POST /batches` — multiple files/URLs → one batch + many projects.
- `GET /projects/{project}` — workspace.
- `PATCH /projects/{project}` — title, selected title, thumbnail, etc.
- `PATCH /projects/{project}/segments/{segment}` — edit text / speaker.
- `PATCH /projects/{project}/speakers/{speaker}` — rename.
- `POST /projects/{project}/metadata` — regenerate (preset, custom prompt).
- `POST /projects/{project}/titles` — more title options.
- `POST /projects/{project}/translations` — `{language}`.
- `POST /projects/{project}/thumbnail` — `{time_sec}` or `{frame_id}`.
- `POST /projects/{project}/chat` — ask a question.
- `POST /projects/{project}/clips` — `{start, end, title?}`; `GET` to download.
- `GET /projects/{project}/export?format=json|srt|vtt|txt|csv|md&schema={id?}&language={code?}`
- `POST /projects/{project}/youtube` — publish.
- `POST /projects/{project}/cancel`, `POST /projects/{project}/retry?stage=`
- `PATCH /projects/{project}/catalogue` — descriptive fields.
- `POST /projects/{project}/subjects`, `PATCH|DELETE /projects/{project}/subjects/{subject}` —
  add, confirm or reject subjects (including AI suggestions).
- `PUT /projects/{project}/rights`, `PUT /projects/{project}/provenance`.
- `POST /projects/{project}/attachments`, `GET|DELETE /projects/{project}/attachments/{attachment}`
  (release forms, sidecars; downloads go through the policy, never a public URL).
- `POST /projects/{project}/versions` — upload an extra version (mezzanine, access copy).
- `POST /projects/{project}/fixity` — run a fixity check now.
- `POST /works`, `GET /works/{work}`, `PATCH /works/{work}` — group projects as reels/parts.
- `POST /projects/{project}/submit` — submit for approval (only from `draft` or
  `changes_requested`).
- `GET /batches/{batch}`, `GET /batches/{batch}/download` (ZIP of all exports + optional thumbnails).
- `GET /settings/export-schemas`, `GET /settings/api-tokens`, `GET /settings/youtube`,
  plus starter-kit profile/appearance settings (dark mode lives here).

**Admin only (`/admin`):**

- `GET /admin` — overview: pending approvals, processing queue, usage this month.
- `GET /admin/approvals` — review queue (submitted projects, oldest first).
- `GET /admin/approvals/{project}` — review page; `POST /admin/approvals/{project}` —
  `{action: approve|reject|request_changes, notes?}` (notes required for reject and
  request_changes).
- `POST /admin/projects/{project}/reopen` — back to `draft`, removed from the public catalogue.
- `GET /admin/projects` — all projects from every encoder (opens the same workspace).
- `GET /admin/users`, `POST /admin/users`, `PATCH /admin/users/{user}` — create encoders and
  admins, change role, deactivate, set the daily processing limit. Admins cannot demote or
  deactivate themselves, so there is always at least one admin.
- `GET /admin/usage` — usage per encoder and per project (minutes, tokens, storage, estimated
  cost) for a date range; CSV download.
- `GET|POST /admin/categories`, `PATCH|DELETE /admin/categories/{category}`.
- `GET|POST /admin/events`, `PATCH|DELETE /admin/events/{catalogueEvent}`.

**Public catalogue (`user` role and guests):**

- `GET /` — catalogue home: latest approved videos, current and recent events, categories.
- `GET /browse?category=&event=&year=&month=&series=&q=&sort=date_desc|date_asc|title` —
  paginated grid; date means `date_shot`, falling back to the event date, then `published_at`.
- `GET /events`, `GET /events/{catalogueEvent:slug}`, `GET /categories/{category:slug}`.
- `GET /watch/{project}` — public player page: access copy, captions, chapters, transcript and the
  public fields only (§1 Roles).
- Public routes are read-only, rate-limited and cached; the cache is cleared when a project is
  approved, reopened or edited by an admin.

### Internal routes (Python → Laravel, signed, no session)

- `POST /internal/projects/{project}/progress`
- `POST /internal/projects/{project}/results/{stage}`
- `POST /internal/clips/{clip}/done`
- `POST /internal/imports/{project}/done`
- `POST /internal/media-files/{mediaFile}/results/{stage}` — probe / checksum / proxy for added versions
- `POST /internal/fixity/{mediaFile}/done`

### Public API (Sanctum tokens, `/api/v1`)

Tokens can be created by encoders and admins only, and act with that user's permissions.

- `POST /projects` (multipart file or `{url}`, options) → `{id, status}`
- `GET /projects/{id}` → status + stages
- `GET /projects/{id}/export?format=…`
- `GET /projects` (paginated)
- Optional: `webhook_url` on create; Laravel POSTs when the project is ready.
- Rate-limit per token.

### Exports

Exporters live in `app/Exporters`, all implement `Exporter::export(Project, ExportOptions): string`.

- **JSON** — the schema in §10, filtered by the chosen `export_schemas.fields` if given.
- **SRT / VTT** — from transcript segments (optionally a translation); include speaker
  prefix if speakers exist. VTT is also served to the player as a `<track>`.
- **TXT** — plain transcript, optional timestamps and speakers.
- **CSV** — one row per segment: `start,end,speaker,text`.
- **Markdown** — title, description, chapters, tags, quotes, transcript, plus a rights summary
  (copyright, licence, restrictions, embargo) when one exists.

---

## 9. Frontend (Inertia + React + TypeScript)

### Pages

Pages are grouped by role folder (see AGENTS.md).

**Encoder (admins use the same pages):**

- `pages/encoder/dashboard.tsx` — history table/grid with thumbnail, title, duration, processing
  status badge, review status badge, created date; search; status filters; "New project" button.
- `pages/encoder/projects/create.tsx` — tabs: **Upload** (dropzone, multiple files → batch, paste from
  clipboard), **URL** (one per line). Options panel: preset, custom prompt, toggles for
  diarization / visual analysis / OCR / objects, translation languages.
- `pages/encoder/projects/show.tsx` — the workspace (layout below), with a "Submit for approval"
  button and the admin's review notes; read-only for encoders once approved.
- `pages/encoder/batches/show.tsx` — batch progress list + "Download ZIP".
- `pages/encoder/settings/*` — export schemas builder, API tokens, YouTube connection.

**Admin:**

- `pages/admin/overview.tsx` — pending approvals, processing queue, usage this month.
- `pages/admin/approvals/index.tsx` — review queue; `pages/admin/approvals/show.tsx` — preview the
  video and the full record, then approve / request changes / reject with notes; review history.
- `pages/admin/projects.tsx` — all projects, any encoder.
- `pages/admin/users/index.tsx` + `partials/` — user list, create, change role, deactivate, limits.
- `pages/admin/usage.tsx` — usage charts and table per encoder/project, date range, CSV download.
- `pages/admin/categories.tsx`, `pages/admin/events.tsx` — manage browse categories and events.

**Public (`user` role and guests):**

- `pages/user/home.tsx` — latest videos, events, categories.
- `pages/user/browse.tsx` — grid with filters: date (year → month timeline), event, category,
  series, search; sort by date or title.
- `pages/user/event.tsx`, `pages/user/category.tsx` — videos in one event or category.
- `pages/user/watch.tsx` — player with captions, chapters, transcript and public metadata.

**All logged-in roles:**

- `pages/all/settings/*` — profile, password, appearance (starter kit).

### Workspace layout (`projects/show.tsx`)

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
  language picker), exposes a ref API (`seek`, `play`, `pause`, `currentTime`).
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
  **Descriptive** (title, description, subjects with confirm/reject for AI suggestions, location,
  date shot with precision, keywords, series/episode, language);
  **Technical** (read-only, per file version: container, streams, scan type, bit depth, chroma,
  colour, audio layout, start timecode, checksums with status);
  **Rights** (copyright, licence, restrictions, territories, embargo, credits, release-form uploads,
  accession number);
  **Provenance** (source carrier, transfer details, preservation-event timeline, fixity history,
  "Check now");
  **Structure** (versions, work/reel grouping, sidecar files, subtitles, shot/scene segments).
- `TimecodeToggle` — switch every time display between seconds and SMPTE timecode
  (`HH:MM:SS:FF`, `;` for drop-frame), using the master's start timecode and frame rate.
- `EmbargoBadge` / restrictions notice — shown on the workspace header, Export and Publish panels.
- `VisualsPanel` — thumbnail picker grid (frames) + "Use current frame"; scenes timeline;
  per-frame description, OCR text, objects, face count.
- `TranslationsPanel` — add a language, view/download translated transcript and subtitles.
- `ChatPanel` — chat with the video; answers show clickable timestamp citations.
- `ClipsPanel` — select a range (from transcript selection, quote, or chapter), name it,
  export, download.
- `ExportPanel` — format picker, schema picker, language picker, live JSON preview, Download.
- `PublishPanel` — connect YouTube, choose privacy, preview title/description/tags/thumbnail,
  publish, show status/link.
- `PipelineProgress` — stage list with status icons + overall progress; listens on Echo
  channel `projects.{id}`; Inertia partial reload (`router.reload({ only: [...] })`) when a
  stage completes.
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
- Zustand for workspace UI state (current time, active segment, search, selection);
  server data comes from Inertia props.
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
    "generator": { "app": "video-metadata-extractor", "version": "0.1.0" },
    "project": {
        "id": "01J...",
        "title": "My video",
        "accessionNumber": "ACC-2026-00042",
        "sourceType": "upload",
        "sourceUrl": null
    },
    "descriptive": {
        "title": "My video",
        "description": "...",
        "subjects": [
            { "name": "Jane Doe", "type": "person", "source": "transcript", "confirmed": true }
        ],
        "location": { "name": "City, Country", "lat": 14.6, "lng": 121.0, "source": "container_gps" },
        "dateShot": { "value": "2024-03-15", "precision": "day", "source": "manual" },
        "keywords": ["..."],
        "series": { "title": "Science Hour", "season": 2, "episodeNumber": 7, "episodeTitle": "Volcanoes" },
        "language": { "detected": "en", "confidence": 0.98 }
    },
    "technical": {
        "files": [
            {
                "id": "01J...",
                "role": "master",
                "name": "demo.mov",
                "sizeBytes": 48211234,
                "mimeType": "video/quicktime",
                "container": "mov",
                "durationSec": 312.5,
                "bitrate": 4500000,
                "startTimecode": "01:00:00:00",
                "checksums": { "md5": "...", "sha256": "...", "status": "ok" },
                "streams": [
                    {
                        "index": 0,
                        "type": "video",
                        "codec": "prores",
                        "profile": "422 HQ",
                        "width": 1920,
                        "height": 1080,
                        "frameRate": "25/1",
                        "scanType": "interlaced_tff",
                        "bitDepth": 10,
                        "chromaSubsampling": "4:2:2",
                        "colorSpace": "bt709",
                        "colorPrimaries": "bt709",
                        "colorTransfer": "bt709",
                        "colorRange": "tv"
                    },
                    {
                        "index": 1,
                        "type": "audio",
                        "codec": "pcm_s24le",
                        "channels": 2,
                        "channelLayout": "stereo",
                        "sampleRate": 48000,
                        "bitDepth": 24,
                        "language": "eng"
                    }
                ]
            }
        ]
    },
    "rights": {
        "copyrightHolder": "...",
        "copyrightYear": 2024,
        "licence": "cc_by_nc",
        "licenceUrl": "https://creativecommons.org/licenses/by-nc/4.0/",
        "restrictions": "...",
        "territories": ["PH"],
        "embargoUntil": null,
        "credits": [{ "role": "Director", "name": "..." }],
        "releaseForms": [{ "id": "01J...", "name": "release-form.pdf", "sha256": "..." }]
    },
    "provenance": {
        "ingestedAt": "2026-01-01T11:58:00Z",
        "sourceCarrier": { "type": "digibeta", "label": "Tape 14", "id": "DB-0014" },
        "transfer": {
            "date": "2025-12-10",
            "facility": "...",
            "operator": "...",
            "deck": "...",
            "settings": "SDI capture, 10-bit uncompressed"
        },
        "events": [
            {
                "type": "ingest",
                "outcome": "success",
                "fileId": "01J...",
                "agent": "system",
                "detail": "...",
                "occurredAt": "2026-01-01T11:58:00Z"
            }
        ],
        "fixityChecks": [
            { "fileId": "01J...", "algorithm": "sha256", "matched": true, "checkedAt": "2026-04-01T02:00:00Z" }
        ]
    },
    "structure": {
        "work": { "id": "01J...", "title": "...", "partNumber": 1, "partLabel": "Reel 1", "totalParts": 3 },
        "versions": [
            { "fileId": "01J...", "role": "master" },
            { "fileId": "01J...", "role": "proxy" }
        ],
        "sidecars": [{ "id": "01J...", "kind": "sidecar", "name": "tape-log.xml", "sha256": "..." }],
        "subtitles": [
            { "language": "en", "source": "transcript" },
            { "language": "ms", "source": "translation" }
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
            "text": "Welcome to the show...",
            "edited": false
        }
    ],
    "translations": {
        "ms": [
            { "id": 1, "start": 0.0, "end": 4.2, "text": "Selamat datang..." }
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
                "description": "A person presenting in front of a whiteboard",
                "ocrText": "Q3 Roadmap",
                "objects": [{ "label": "laptop", "confidence": 0.91 }],
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

Custom export schemas are lists of dot-paths (e.g. `["project.title", "ai.description",
"ai.tags", "transcript"]`); the exporter keeps only those keys.

Shot/scene segments live in `structure.segments` (not under `visual`). Times are always seconds;
timecode strings (`tcIn`, `tcOut`, `startTimecode`) are included alongside for archival use.

---

## 11. Archival metadata

Every project carries a catalogue record in five categories. The table says what each one answers,
which fields it holds, and how the system fills it in.

| Category | Question it answers | Fields | How we capture it |
|---|---|---|---|
| **Descriptive** | What is this? | Title, description, people/subjects, location, date shot, keywords, series/episode, language | AI suggests title, description, keywords, subjects (from transcript and OCR) and language. Container tags suggest date shot and GPS location. The user confirms suggestions and fills in series/episode. |
| **Technical** | How is it encoded? | Codec, container, resolution, frame rate, scan type, bit depth, chroma subsampling, colour space/primaries/transfer, audio channels and layout, sample rate, duration, start timecode, file size | Automatic: the `probe` stage (ffprobe + mediainfo) for every file version. Read-only in the UI. |
| **Administrative / rights** | Who owns it and how can it be used? | Copyright holder, licence, embargoes, restrictions, credits, release forms, accession number | Manual entry in the Rights tab. Accession number auto-assigned at ingest from `ACCESSION_NUMBER_FORMAT` (editable, unique). Release forms uploaded as private attachments. Embargo enforced server-side. |
| **Preservation / provenance** | What has happened to it? | Checksums (MD5/SHA-256), ingest date, source carrier (e.g. Digibeta tape, camera card), transfer/digitisation details (deck, operator, settings), migrations, fixity-check history | Checksums and ingest date are automatic (`checksum` stage). Source carrier and transfer details are manual. Ingests, transcodes, migrations and fixity checks are logged automatically as preservation events. |
| **Structural** | How do the pieces relate? | Reels and parts, versions (master, mezzanine, proxy), sidecar files, subtitles, shot/scene segments with timecode in and out | Versions tracked as `media_files` (proxy generated automatically). Reels/parts grouped into a `work`. Sidecars uploaded as attachments. Subtitles come from transcripts and translations. Shot segments come from the `scenes` stage with timecode in/out; scenes are user-defined groups of shots. |

### Rules

- **Suggested vs confirmed.** Anything AI or file tags produce for descriptive fields is a
  suggestion (with a `source`) until a user confirms it. Exports include the source.
- **People and subjects come from what is said or written** (transcript, OCR, user entry), never
  from faces. This keeps the no-face-recognition rule in §14.
- **Date shot.** Container `creation_time` is often the encode or copy date, not the shoot date.
  Show it as a suggestion with that warning. Store a precision (`day|month|year|circa`), because
  archival dates are often approximate.
- **Timecode.** Read the start timecode from the container tag or the timecode (`tmcd`) stream;
  default `00:00:00:00`. Convert seconds ↔ SMPTE using the frame rate, with drop-frame (`;`
  separator) for 29.97 and 59.94 fps. Exports include both seconds and timecode.
- **Scan type, bit depth, chroma subsampling.** Derive from ffprobe (`field_order`,
  `bits_per_raw_sample`, `pix_fmt`), fall back to mediainfo, and store `unknown` rather than guess.
- **Checksums.** Compute MD5 and SHA-256 in one streaming pass right after the file is stored,
  before any other stage touches it. The user may paste an expected checksum from a source
  manifest; a mismatch flags the project and logs a failed event. Every derived file (proxy, clip,
  added version) gets its own checksums.
- **Fixity checks.** A scheduled job re-hashes every master and mezzanine file every
  `FIXITY_CHECK_INTERVAL_DAYS`, and the Provenance tab has a "Check now" button. Each result is
  stored in `fixity_checks` and logged as an event; a mismatch flags the project and notifies the
  owner.
- **Preservation events are append-only.** No update or delete paths, in code or in the UI.
- **Embargo and restrictions.** While `embargo_until` is in the future, the public catalogue,
  YouTube publishing, public-API export and batch ZIPs exclude the project (the `distribute`
  policy, §8). The workspace,
  Export and Publish panels show the embargo and restriction text, and exports include them.
- **Release forms contain personal data.** Private disk, policy-checked downloads only, never in
  public-API responses or batch ZIPs.
- **Master and mezzanine files are never deleted automatically.** Cleanup jobs only remove
  temporary files (extracted audio, frames, old proxies that can be regenerated).
- **Standards alignment.** Field names follow PBCore / EBUCore (descriptive, technical) and
  PREMIS (events, fixity) closely enough that standards-based XML exports can be added later.
  These exports are not in scope yet; log them in `DECISIONS.md` under "Later".

---

## 12. Environment variables

### `web/.env` (additions)

```
APP_NAME="Video Metadata Extractor"
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
summary of what to try manually.

### Phase 0 — Scaffold

- Monorepo layout, Docker Compose with all services, Laravel React starter kit, Python
  FastAPI + Celery skeleton, `/health` endpoint, HMAC signing on both sides with tests,
  Reverb + Echo wired up with a test event, Horizon dashboard reachable.
- **Done when:** `docker compose up` brings everything up, Laravel can call `/health`, Python
  can hit a signed Laravel test callback, and a broadcast event shows in the browser.

### Phase 1 — Core app

- Upload (dropzone + paste), storage, `projects` + `project_stages` + `transcript_segments` +
  `metadata_generations` + `media_files` + `media_streams` + `preservation_events`.
- Pipeline stages: `probe` (full technical metadata, §11), `checksum` (MD5 + SHA-256, logged as
  ingest and checksum events), `extract_audio`, `transcribe` (OpenAI, with chunking for > 25 MB),
  language detection, `generate_metadata` (generic + youtube presets; titles, description,
  summary, tags, keywords, hashtags, chapters).
- If the uploaded master can't play in a browser (e.g. ProRes, MXF), the workspace shows a notice;
  the proxy arrives in Phase 3.
- Roles: `users.role` (`admin|encoder|user`), `role` middleware, route files per role
  (`admin.php`, `encoder.php`, `user.php`, `all.php`), admin user management (create encoders and
  admins, change role, deactivate). Encoders see only their own projects; admins see all.
  `usage_records` written for every transcription and LLM call (the usage dashboard comes in
  Phase 4).
- Workspace: VideoPlayer, synced TranscriptPanel, click-to-seek, **pause → copy current line**,
  transcript search, inline editing, MetadataPanel (edit fields, regenerate), PipelineProgress
  over Reverb.
- Dashboard history list.
- JSON export.
- **Done when:** upload a 5-minute video → see live progress → transcript syncs with playback →
  pause and copy a line → edit a segment → regenerate metadata → download valid JSON whose
  `technical` block lists every stream and both checksums (checksums match a manually computed
  SHA-256). An admin creates an encoder account, and that encoder cannot open another encoder's
  project.

### Phase 2 — More AI output and export formats

- All platform presets, multiple title options + "generate more", custom prompt, tone +
  content flags, key quotes.
- Translations (async, per language).
- Exporters: SRT, VTT, TXT, CSV, Markdown; custom export schemas (settings page + picker).
- Captions in the player (VTT track, language switcher).
- Keyboard shortcuts + shortcuts dialog. Dark mode check across all new UI.
- **Done when:** switch to TikTok preset and regenerate, translate to another language, show
  captions in that language, export SRT/VTT/CSV/MD, export JSON with a custom schema.

### Phase 3 — Archival metadata

- Tables: `catalogue_records`, `subjects`, `rights_records`, `attachments`, `provenance_records`,
  `fixity_checks`, `works`. `preservation_events` now also logged for transcodes, metadata changes
  and fixity checks.
- `proxy` stage for masters that can't play in a browser; upload extra versions (mezzanine, access),
  each probed and checksummed via `/jobs/ingest-file`.
- `generate_metadata` suggests subjects and location; container tags suggest date shot and location.
  Suggestions need user confirmation.
- Accession numbers, release-form and sidecar uploads (private disk), scheduled + manual fixity
  checks with owner notification on mismatch.
- Embargo check as one policy method (`distribute`) with tests. Later phases' publish, API export
  and batch ZIP must call it.
- ArchivePanel (Descriptive | Technical | Rights | Provenance | Structure), TimecodeToggle,
  EmbargoBadge; dashboard search by accession number and an "embargoed" filter.
- JSON export includes all five archival blocks; Markdown export includes the rights summary.
- **Done when:** upload a ProRes or MXF master → it plays through the proxy; the Technical tab
  shows scan type, bit depth, chroma, colour and audio layout for master and proxy; confirm an AI
  subject suggestion; fill rights with an embargo and see the badge (policy covered by tests);
  record a Digibeta transfer; run "Check now" and see the result in fixity history; group two
  reels into a work; export JSON containing descriptive, technical, rights, provenance and
  structure.

### Phase 4 — Approvals, usage and public catalogue

- Approval workflow: `review_status`, `project_reviews`, "Submit for approval", admin review queue
  with video preview and notes, request changes / reject / approve, reopen. Approved records are
  read-only for encoders.
- `categories` and `catalogue_events` (admin CRUD); encoders assign a category and an event.
- Admin overview and usage dashboard (per encoder / project, date range, CSV download). Daily
  processing limits per encoder, checked before processing starts.
- Public catalogue in `routes/user.php`: home, browse (date timeline, event, category, series,
  search, sort), event and category pages, watch page using the access copy. Only projects passing
  `distribute`; public responses expose only the public fields; cached and rate-limited.
- Public sign-up creates `user` accounts only (when `ALLOW_PUBLIC_REGISTRATION=true`).
- **Done when:** an encoder submits a project → the admin requests changes with a note → the
  encoder fixes it and resubmits → the admin approves → the video appears on the public site under
  its event and date, plays from the proxy, and shows no internal fields; reopening it removes it
  from the public site; an embargoed approved project stays hidden; an encoder over the daily
  limit cannot start processing; the usage page shows that encoder's minutes and estimated cost.

### Phase 5 — Visual analysis

- Stages: `scenes`, `frames`, `vision`, `ocr`, `objects` (store face **counts** only — no face
  recognition or identity matching).
- Shots from `scenes` stored with timecode in/out in `structure.segments`; users can group shots
  into named scenes.
- VisualsPanel: thumbnail picker, "use current frame", scenes on the timeline, frame details.
- Feed visual context into metadata generation when enabled.
- **Done when:** a slide-based video yields OCR text, frame descriptions, scene markers with
  timecodes, a chosen thumbnail, and metadata that mentions on-screen content.

### Phase 6 — Workflow

- Batch upload + batch page + ZIP download (embargoed projects excluded via the `distribute` policy).
- URL import via yt-dlp (show a notice: only import videos you have the rights to).
- Speaker diarization (pyannote or AssemblyAI) + speaker rename + colours.
- Local transcription provider (faster-whisper) behind the same interface.
- Project history polish: search, filters, delete, retry failed stage, cancel.
- **Done when:** upload 3 videos as a batch, watch them process, download one ZIP; import from a
  URL; see and rename speakers.

### Phase 7 — Advanced

- Clip export (from transcript selection, quote, or chapter); each clip is probed and checksummed.
- Chat with the video (embed stage, pgvector search, cited answers).
- Public API with Sanctum tokens + settings page + rate limits + optional webhook. Export
  respects the embargo policy; release forms are never returned.
- YouTube: OAuth connect, publish with title/description/tags/thumbnail/privacy, status tracking.
  Publishing respects the embargo policy.
- **Done when:** cut and download a clip, ask "what did they say about X?" and click the cited
  timestamp, create a project through the API with a token, publish a test video as private.

---

## 14. Non-functional requirements

- **Cost controls:** max file size, max duration, max batch size, per-user daily limits (config).
  Show an estimated cost/usage line per project if easy. Cache LLM outputs.
- **Reliability:** every stage is idempotent and retryable; Celery tasks have time limits and
  retries with backoff (tenacity for provider calls); failed stages can be retried individually.
- **Cleanup:** scheduled job deletes temp audio/frames for projects older than N days (config);
  soft-deleted projects purge files after N days. Master and mezzanine files are never removed by
  the temp-file cleanup, and every purge is logged as a `deletion` preservation event.
- **Archival integrity:** checksums at ingest for every file, scheduled fixity checks,
  append-only preservation events, embargo enforced server-side (§11).
- **Security:** policies on every project route; signed internal callbacks; validate MIME type
  and size on upload; sanitise filenames; no API keys in the browser; rate-limit public API and
  AI-triggering endpoints.
- **Privacy:** no face recognition; store face counts only. Make it clear in the UI that video
  content is sent to third-party AI providers when hosted providers are selected.
- **Observability:** structured logs in both services with `project_id` on every line; Horizon
  for Laravel jobs; Flower optional for Celery.
- **Testing:** Pest feature tests for routes, policies, exporters, internal callbacks
  (signature verification); pytest for each pipeline stage with small fixture media files and
  mocked providers; at least one end-to-end happy path per phase.
- **Code quality:** Pint, ESLint/Prettier, ruff, mypy all pass. No `any` in TypeScript without a
  comment explaining why.

---

## 15. Working agreement for the agent

1. Read this whole file before writing code.
2. Work one phase at a time. Use small, focused commits with clear messages.
3. Keep the TypeScript types, Pydantic schemas, and §10 JSON schema in sync — if you change one,
   change all three.
4. Prefer official docs over memory for package APIs (Laravel, Inertia, Reverb, OpenAI,
   Anthropic, PySceneDetect, etc.). Log any deviation from this spec in `DECISIONS.md`.
5. Don't add features that aren't in the current phase. Put ideas in `DECISIONS.md` under
   "Later".
6. At the end of each phase: run all tests and linters, update `README.md`, then stop and report.
