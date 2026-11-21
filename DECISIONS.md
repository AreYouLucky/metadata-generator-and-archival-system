# Decisions

Choices made while building, and any deviation from [PROJECT_SPEC.md](PROJECT_SPEC.md). Newest
phase first.

## Phase 3 — Archival metadata (built)

### Catalogue

- **`PATCH /records/{record}` saves one Archive sub-tab at a time:** every rule is `sometimes`,
  and groups are sent whole (project with series, agency with unit, the location chain). Paired
  fields (latitude/longitude, date and its precision, disposition date and action) are always
  checked, so leaving one out can't slip past `required_with`. Each save logs `updated` with the
  names of the changed fields.
- **Accession numbers** are assigned at ingest from `ACCESSION_NUMBER_FORMAT` using a locked row
  in a new `sequences` table (works on PostgreSQL and SQLite). A number typed by hand is skipped
  by the generator. `archive:assign-accession-numbers` numbers older records once, oldest first.
  The number is editable (letters, digits and `. _ / -`) and unique.
- **Subjects and keywords:** ones added by hand are confirmed at once; AI ones are created
  unconfirmed (source `ai`) when metadata is stored and wait for a tick; rejecting deletes them.
  The AI's keywords become archive keyword suggestions too. Every change is in the audit log.
- **Location suggestion:** the AI names the place; Laravel matches the names (case-insensitive,
  top-down, also the region's description such as "Central Visayas") to location ids and keeps
  them with the suggestion. A level that doesn't match stops the chain; the encoder applies it.
- **Container tags** give two hints, shown with a "Use" button: the creation date (with a warning
  that it's often the copy date; 1904/1970 and future dates are ignored) and ISO 6709 GPS.
- **"Fill with AI" (owner's request)** in Archive → Descriptive suggests the title, alternative
  title, catalogue description, genre and video type (only names from the admin lists), country
  and recording date (only a real past date; from the series or project dates when the video
  doesn't say). It reads the active generation's summary, chapters and keywords plus the project
  and series, never the transcript (a few hundred tokens). Queued like "Generate more"; the
  suggestions fill the form unsaved, with Undo, and a date from the AI is stored with source `ai`.
- **GPS map picker (owner's choice):** `leaflet` + `react-leaflet` with OpenStreetMap tiles, no
  API key. Click the map to drop the pin (a vector circle; Leaflet's image icon breaks under
  bundlers), or "Use my location" (the browser asks first). The map loads lazily (its own ~45 kB
  gzipped chunk) only when the Location section opens, and is isolated so its layers can't cover
  menus or dialogs. OSM's tile policy suits light internal use; for public or heavy traffic set
  `VITE_MAP_TILE_URL` / `VITE_MAP_ATTRIBUTION` to your own tile server or a provider. Viewing the
  map sends tile requests (the area shown, not the record) to that provider.
- **Credits:** the Archive tab holds the agency and unit; people stay in the existing Credits tab
  (one place to edit them).
- **Works:** `POST /works`, `GET|PATCH /works/{work}`; a record joins a work through the record
  update (`work_id`, part number and label). Encoders use only their own works; admins any.

### Files and integrity

- **The proxy stage runs last** in the chain (after metadata) on the normal worker, so a long
  transcode never delays the transcript. It runs only when the master isn't browser-playable:
  the container isn't MP4/MOV/WebM, a codec isn't H.264/VP8/VP9/AV1 + AAC/MP3/Opus/Vorbis, or the
  H.264 is 10-bit or 4:2:2 (which most browsers can't decode). The copy is H.264 (CRF 23, 8-bit
  4:2:0) + AAC in MP4, `faststart`, never upscaled, deinterlaced with yadif when the master is
  interlaced, at `records/{id}/proxy/access.mp4` (written to a `.part` file first). It gets its
  own checksums and technical record, logged as `transcoded`; a new proxy replaces the old row.
  The media route streams the proxy only when the master can't play.
- **Extra files** (mezzanine, access) go through the same chunked upload, then
  `/jobs/ingest-file` checksums and probes them; results arrive on
  `/internal/record-files/{file}/results/{checksum|probe|failed}`. The master can't be written
  through that route.
- **Ingest fixity:** when an expected checksum was supplied, the comparison at ingest is also
  stored in `fixity_checks` (`triggered_by: ingest`), for masters and added files.
- **Fixity checks** re-hash masters and mezzanines (proxies and access copies can be remade).
  The AI service only computes checksums; Laravel compares them (`hash_equals`), stores one row
  per algorithm, logs `checksum_verified`/`checksum_failed`, and on a mismatch or a missing file
  sets `checksum_status = mismatch` and emails the owner and every active admin. A check that
  couldn't run is logged, not counted as a mismatch.
- **Scheduled checks:** `archive:fixity` runs daily at 02:00 and queues files never checked since
  ingest or not checked within `FIXITY_CHECK_INTERVAL_DAYS`, oldest first, at most 500 per run, so
  a big archive is spread over several nights. One check per file at a time (`ShouldBeUnique`).
- **"Check now"** needs only view access (it changes nothing unless a file has changed) and is
  limited to 6 a minute.
- **Attachments** (release forms, contracts, sidecars, documents) are stored on the private media
  disk under a generated name with their SHA-256, downloaded only through the record policy, and
  limited to documents, images, subtitles and sidecars (`MAX_ATTACHMENT_MB`, default 50). HTML and
  executables are refused.

### Distribution policy

- `RecordPolicy::distribute(?User, Record)` needs: approved with `published_at` set, not deleted,
  `unclassified`, and rights that allow public access (public, permission approved, no embargo in
  force). There is no `is_approved` column; approval is `review_status = approved`. Nothing calls
  it yet: the public catalogue, YouTube, API export and ZIPs (Phases 4, 6, 7) must.

### Workspace and lists

- A new **Archive** inspector tab with eight sections; the tab shows how many AI suggestions wait.
- **TimecodeToggle (TC)** in the transport bar switches the transport clock and the transcript
  times to SMPTE timecode from the master's start timecode and frame rate (drop-frame with `;` at
  29.97/59.94; 25 fps if the rate is unknown).
- The header shows the accession number, a classification badge (above unclassified) and an
  embargo badge (while one is in force); so does the record list, which also searches accession
  numbers and filters embargoed or classified records.
- **Disposition review** (`/admin/dispositions`, admin sidebar): records whose disposition date has
  passed, oldest first. A page instead of a scheduled job: the list is always current, and nothing
  is ever destroyed automatically.

### Later

- Shot/scene segments in the Structure tab arrive with Phase 5 (visual analysis).
- An in-app notification for fixity mismatches (email only for now).
- Moving a record to another project resets the series; series of other projects aren't listed
  until the project is changed.

## Phase 2 — More AI output and export formats (built)

### Presets (limits checked 2026-11-22)

- **TikTok:** 2,200-character caption. The Content Posting API's limit is 2,200; in-app captions
  reportedly allow 4,000, but the API limit is the safe one. 3–5 hashtags.
- **Instagram:** 2,200-character caption, **at most 5 hashtags**. Instagram reportedly cut the
  limit from 30 to 5 in December 2025; this wasn't confirmed on an official Meta page, so 5 is
  used because it is valid either way.
- **LinkedIn:** 3,000 characters, 3–5 hashtags, professional tone.
- TikTok, Instagram and LinkedIn have no title field (`title_max` null): their titles are short
  working titles for the archive and are not cut. They still get up to 10 tags for the archive.
- The guidance text of each preset says how much of the caption shows before "more".

### AI output

- **Quotes, tone and content flags come from the same metadata call** (no extra request) and
  belong to the cached, preset-independent "core", so switching preset keeps them. The core's
  cache version moved to `v2`: the first regeneration of a Phase 1 record reads its transcript
  once more.
- **Quote times are aligned in code, not by the model.** The model sees one time per ~30 s block,
  returns each quote word for word with its block time, and the AI service finds the transcript
  lines it came from (exact start and end). A quote whose opening matches no line is dropped,
  so made-up quotes don't reach the record. For long transcripts the map step picks verbatim
  quotes per part, because the final call only sees summaries.
- **Tone** is one of ten fixed values (structured output guarantees it); **content flags** are
  profanity plus up to 5 sensitive-topic labels. They are shown as badges. Speakers on quotes
  stay empty until diarization (Phase 6).
- **"Generate more" titles** (`POST /records/{record}/titles` → `/generate/titles`) are written
  from the generation's summary only (a few hundred tokens), never cached, and told the existing
  titles to avoid. The job appends them to the generation under a row lock and broadcasts
  `record.titles-generated`; a cache lock stops double requests. A generation holds at most 12
  titles. The request names the generation, so a regeneration in between returns 409.
- Older generations get `quotes: []`, `tone: null`, `contentFlags: null` when shown; the new
  fields are optional in validation, so they still save.

### Translations

- **Async with callbacks**, as §7 suggests for long transcripts: `StartTranslationJob` →
  `POST /jobs/translate` → a Celery task on the normal (non-GPU) queue → signed callbacks
  `/internal/records/{record}/translations/{transcript}/progress|result`. The transcript route
  value is scoped to the record and must not be the original.
- **Only line ids and text are sent**, in batches of up to 60 lines / 6,000 characters
  ("index|text", indexes restarting per batch). Laravel copies each line's timing, order and
  speaker from the original line, so the model can't break a timestamp. Lines the model skips
  are asked for once more, then keep the source text.
- **Model:** the main model (`LLM_TRANSLATION_MODEL`, default `gpt-6.2-sol`) rather than the light
  one, because Filipino and regional languages are where a small model's subtitles read badly.
  Usage is summed per kind, so a long translation writes two usage rows.
- **Languages** are a fixed list in `config/archive.php` (`translation_languages`, code → English
  name; the name is what the model is told). The original's language is refused. Translating
  again replaces the lines (useful after editing the original).
- `record_transcripts` gained `progress` and `error`, so a failure keeps its message after a
  reload. A re-run of the pipeline whose transcript comes out in a language that already has a
  translation replaces that translation (the table is unique per record and language).
- **Not built:** choosing translation languages at upload (needs the pipeline options stored on
  the record; see "Later").

### Exports

- `ExportFormat` enum maps each format to its exporter. SRT and VTT add the speaker (`Name: ` /
  `<v Name>`); VTT escapes `&`, `<`, `>` and `-->`. CSV is `start,end,speaker,text` with a UTF-8
  BOM (Excel) and an apostrophe before cells starting with `= + - @` (CSV injection). Markdown
  escapes formatting characters in record text, so it can't turn into links, images or HTML.
- **Markdown uses the record's description and falls back to the AI description**; it also
  includes the AI summary.
- **The JSON export gained two §10 fields** Phase 1 left out: `record.isApproved` (derived from
  `review_status`; there is no `is_approved` column) and `endDate` on project and series. The
  generator version is now 0.3.0; `schemaVersion` stays 1.0 (only additions).
- **Custom schemas** keep only the chosen dot-paths, from a fixed list of 60 paths
  (`ExportSchemaFields`), in document order. `schemaVersion` and `generatedAt` always stay, so a
  consumer can tell which shape it got. A chosen sub-field of an empty block keeps the block as
  `null`. Schemas are personal (admins included); one may be the default, which the Export panel
  picks first. Without a `schema` parameter the export is always the full document.
- **Previews aren't logged.** The Export panel fetches `export?…&preview=1` (inline, plain text,
  no-store); only downloads write an `exported` audit entry, now with the language and schema.
- **Captions** use their own route (`GET /records/{record}/captions/{language}`, inline VTT,
  not cached, not logged) rather than the export route, so playing a video doesn't fill the
  audit log with exports. Tracks reload after a line is edited or deleted.

### Workspace

- New inspector tabs **Languages** (the spec's TranslationsPanel; "Translations" didn't fit the
  tab bar) and **Export**. The header's "Export JSON" button now opens the Export tab.
- **Keyboard shortcuts:** Space/K, J/L, ←/→ (previous/next line), C and Shift+C (copy the current
  line, with its time), / (search), E (edit the current line), ? (shortcuts dialog). **T (set the
  current frame as thumbnail) waits for Phase 5**, which adds frames and thumbnails; the dialog
  lists only keys that work. The arrow keys move between transcript lines, not frames.
- Export schemas live under **Settings → Export schemas** (the settings layout, shown to encoders
  and admins only); the route is in `routes/encoder.php`.

### Later

- Translation languages chosen at upload (store the pipeline options on the record).
- Detect translations stuck in "processing" when a worker dies (same as stages).
- Three Phase 1 PHPStan errors remain in `Admin/LogController` (paginator generics and
  `withTrashed()` on a BelongsTo); untouched by Phase 2.

## Phase 1 — Core app (built)

### Token budget (owner: "reduce token usage as much as possible")

- **Models, from OpenAI's docs on 2026-11-21:** `LLM_MODEL=gpt-6.2-sol` ($2 / $10 per 1M tokens)
  for the metadata call, `LLM_LIGHT_MODEL=gpt-6-luna` ($0.10 / $0.50) for the map step of long
  transcripts, `VISION_MODEL=gpt-6-luna` (Phase 5). Sol was chosen over Luna for the final call
  because Filipino/Taglish and strict JSON are where quality shows; a metadata run costs cents.
  Prices live in `ai-service/app/pricing.py` and feed `usage_records.estimated_cost`.
- **How the AI service keeps tokens down** (`ai-service/app/services/metadata.py`):
    - Transcription stays local (no audio tokens).
    - The transcript is sent as ~30 s blocks with one `[m:ss]` each, not per-segment timestamps.
    - Titles, description, summary, tags, keywords, hashtags and chapters come from **one**
      structured call (Responses API, `responses.parse`), with `max_output_tokens` and
      `reasoning.effort=low` (Sol's lowest allowed value).
    - That call also yields a preset-independent "core" (summary, chapters, keywords), cached in
      Redis per transcript for 30 days. Regenerating, or switching preset, sends only the core
      (~1–2k tokens), not the transcript. An edited transcript has a new hash and is read again.
    - Identical requests are answered from the Redis result cache (`hash(transcript + preset +
      prompt + model)`, as the spec asks). "Regenerate" sends `fresh: true`, which skips the
      result cache (otherwise it would return the same text) but still uses the core.
    - Static instructions come first, then the transcript, then the preset and custom prompt, so
      OpenAI's automatic prompt caching bills repeated prefixes at the cached rate.
    - Transcripts over `LLM_MAX_TRANSCRIPT_TOKENS` (60k, estimated at 3 characters per token
      because Filipino tokenises worse than English) are summarised in parts with the light
      model first.
    - Preset limits are enforced in code after the call (titles, description, tag count and
      total length, hashtags, chapter spacing), so the model is never re-asked just to shorten.
- **Metadata language:** the transcript's main language; Taglish gets English unless the
  encoder's instructions say otherwise.

### OpenAI transcription provider

- `whisper-1` (verbose_json, segment timestamps) is the only OpenAI model documented to return
  segment timestamps, and it is **deprecated, shutting down 2027-02-26**. The provider is built as
  the spec asks (chunks of ~10 minutes with 2 s overlap over 25 MB) but is not a long-term
  fallback: production needs a GPU server or the AssemblyAI provider (Phase 6).

### Pipeline

- **Stage order:** `checksum` → `probe` → `extract_audio` → `transcribe` → `generate_metadata`, one
  Celery chain. Checksums run first so nothing reads the file before it is hashed. A failing stage
  doesn't break the chain: dependent stages report `skipped`, independent ones still run.
- **Record status** is derived from the stages: `queued` → `processing` → `ready`, or `failed`
  once every stage has finished and one failed (`processing_error` names it).
- **Two workers:** `ai-worker` (queue `celery`: checksums, probing, audio, LLM) and
  `ai-worker-gpu` (queue `gpu`, concurrency 1: transcription), so two copies of the model never
  share the 6 GB card and an LLM call never waits behind a long transcription.
  `docker-compose.gpu.yml` now gives the GPU to `ai-worker-gpu`. Downloaded models persist in the
  `whispermodels` volume.
- **The image installs cuBLAS/cuDNN 9 from pip** (`nvidia-*-cu12`), as faster-whisper's README
  recommends, instead of a CUDA base image. `--build-arg LOCAL_WHISPER=false` builds without them.
  Dependencies install against a stub package first, so code changes don't re-download them.
- **ffmpeg and ffprobe are called with `subprocess`**, not `ffmpeg-python` (unmaintained, and a
  thin wrapper). `pymediainfo` is the fallback for scan type, bit depth and chroma.
- **Max duration** (`MAX_VIDEO_MINUTES`) is checked in the probe stage; a longer video fails
  probing, so nothing is transcribed or sent to OpenAI.
- **Progress and stage events broadcast immediately** (`ShouldBroadcastNow`, wrapped in
  `rescue()`), so a slow queued job can't delay them and a Reverb outage can't fail a callback.
- **Callbacks to Laravel retry** network errors, 429 and 5xx (6 attempts, re-signed each time);
  throttled progress ticks are best effort.
- **Platform limits live only in `config/presets.php`.** Laravel sends them with every
  generation; Python has no copy. Phase 1 enables `generic` and `youtube`.

### Uploads and media

- **Chunked uploads (8 MB):** PHP's `post_max_size` here is 40 MB. The session (owner, size, bytes
  received) is kept in the cache for a day; parts are in `uploads/` on the private media disk; an
  out-of-order chunk gets 409 and the browser resumes from the server's byte count. Only the user
  who started an upload can add to or claim it (others get 404).
- **The real file type is checked after upload** (finfo on the content): `video/*` or
  `application/mxf`, otherwise the part is deleted and the request fails with 422.
- **Masters are stored at `records/{record}/master/{file}.{ext}`** with a generated name; the
  original file name is kept only as metadata. They stream through `records/{record}/media`
  behind the policy (Range requests work), never from a public URL.
- **react-dropzone was not added:** drag-and-drop, paste and the file picker are a few lines.

### Roles, routes and accounts

- **Encoder URLs have no `/encoder` prefix**, matching the spec (`/records/...`). The record
  history is `/records`; `/dashboard` (Fortify's home for every role) sends encoders and admins
  there and still shows the placeholder to viewers until the catalogue (Phase 4).
- **Admin-created accounts are verified immediately** and start with a temporary password the
  admin gives the person. Admins create encoders and admins; viewers sign up themselves.
- **Deactivated accounts are blocked at login** (`Fortify::authenticateUsing`, the reason is shown
  only after a correct password) **and signed out on their next request** (`EnsureUserIsActive` in
  the web group). Two Phase 0 tests that expected a 403 for a deactivated admin now expect the
  sign-out redirect.
- **The daily processing limit** can be set per user now; it is enforced in Phase 4.

### Projects come first (owner's request)

- **Every upload is filed under a project (event or programme); the series is optional.** The
  spec had projects and series as an optional Phase 3 step. The owner wants the event chosen
  before a video is uploaded, so `project_id` is required when a record is created, and a series,
  when given, must belong to that project (both checked in `StoreRecordRequest` before the upload
  is claimed, so a refused request can be retried with the same upload).
- **Pulled forward from Phase 3:** a Projects page (list with search, series and video counts), a
  project page (details, series, the encoder's own videos filed there, "Upload to this project"),
  and create forms for projects and series. The Import media inspector has a project picker
  (search or create on the spot) and a series picker (or create). The forms take title, date,
  venue, description and the region → province → city/municipality → barangay chain (owner's
  request). Editing projects and the agency stay in Phase 3.
- **The location pickers were pulled forward too:** the spec's public, cached
  `/locations/regions|provinces|cities|barangays` lookups (routes/user.php, 120 requests a minute,
  cached a day on the server and in the browser) and a reusable `LocationPicker`
  (`components/all/`). Every level is optional, but each one given must belong to the level above
  (`App\Support\LocationRules`, shared by projects, series and, in Phase 3, records). NCR's
  cities sit under the province "Metro Manila" in the seed data, so the chain has four levels
  everywhere.
- **Projects are shared:** any active encoder or admin can see every project and add series, since
  encoders from different units cover the same events. Records stay private to their encoder.
- **Project editing, agency, thumbnail and deleting were pulled forward from Phase 3** (owner's
  request). The project form takes first and last day, agency and a cover image (private `media`
  disk, served through the policy). Only the creator or an admin edits.
- **Deleting a project is a soft delete** (`projects.deleted_at`). The project and its series leave
  the lists, the picker and the upload rules; videos already filed under it keep pointing at it
  and still show its name (`Record::project()` includes deleted projects). The creator may delete
  only while nothing is filed under it, since other encoders' videos may be; admins always may.
  There is no restore screen yet.
- **Uploads start from a project or one of its series** (owner's request): each series row has
  "Upload to this series" (`/records/create?project=…&series=…`, the series checked against the
  project), and "New record" left the sidebar.

### Sidebar and list pages (owner's request)

- **The sidebar is grouped:** "Archive" (Projects, Agencies, Pending reviews) for encoders and
  admins, and "Administration" (Users, Logs, System status) for admins only. Records left the
  sidebar but keeps its URL; viewers still see only Dashboard.
- **The Agencies page is readable by every encoder** (`GET /agencies`,
  `pages/encoder/agencies-page/`) instead of the spec's admin-only `pages/admin/agencies.tsx`;
  adding, renaming and deleting stay on the admin routes and buttons show only for admins.
- **Pending reviews** (`GET /reviews`) lists submitted records oldest first: encoders see their own,
  admins everyone's. It is read-only until the Phase 3 approval workflow; the spec's
  `/admin/approvals` queue will replace or extend it.
- **Logs** (`GET /admin/logs`, admin only) shows `video_audit_logs` and `access_logs`, newest first,
  filterable by action, including entries for deleted records. IP addresses appear only here.

### Editor-style media screens (owner's request)

- **The workspace and the New record page look like a video editor** (CapCut / Vegas style,
  without copying either product's look): a dark shell with docked panels. The workspace has the
  transcript on the left, the preview monitor with transport controls in the middle, the metadata
  inspector on the right and a timeline along the bottom. New record has a media bin, a source
  monitor that plays the file locally before upload, an import-settings inspector and a
  render-bar style status bar. The spec's stacked layout (§9) is replaced; later phases add
  tracks (scenes, speakers, clips) and inspector tabs instead of new cards.
- **The editor stays dark in both themes**, like editing software: the `dark` class is scoped to
  the editor's root, so the theme tokens switch only inside it. Popovers rendered in a portal
  (select menus) follow the app theme.
- **Timeline:** time ruler (click or drag to scrub), chapters, transcript and audio waveform
  tracks, a playhead that pages along while playing, and logarithmic zoom from "fit" to 200 px per
  second. Only the visible part of each track is rendered, and the waveform canvas is the size of
  the visible window, so a 3-hour video stays fast.
- **The waveform is computed on the server, not with wavesurfer.js.** wavesurfer would make the
  browser download and decode the whole master (up to 2 GB). The `extract_audio` stage writes
  `records/{id}/waveform.json` (10 peaks per second, 0–100; an hour is ~36,000 integers) outside
  the temporary work folder, and `GET /records/{record}/waveform` serves it behind the policy. A
  waveform failure never fails the stage. No new dependency.
- **The player now has custom transport controls** (back/forward 5 s, play/pause, time display,
  mute, full screen) instead of the native controls; clicking the picture toggles playback.

### Frontend

- **zustand and @tanstack/react-virtual were installed** (both listed in the spec): workspace
  state, and a virtualised transcript for long videos.
- **The shadcn CLI could not run** (it detected `pnpm-workspace.yaml` and tried pnpm, which isn't
  installed), so `textarea` was added by hand in the shadcn style.
- **Captions and keyboard shortcuts** for the player come with Phase 2.
- **Metadata edits are saved explicitly** ("Save changes") rather than autosaved, because a
  regeneration replaces the form. Transcript lines save on Enter.
- **The upload page's AI notice is static text** describing the default setup (local
  transcription, transcript sent to OpenAI). Laravel doesn't read the AI service's provider
  settings.
- **Model docblocks now say `CarbonImmutable`** (aliased as `Carbon`): the app uses
  `Date::use(CarbonImmutable::class)`, so the old `Illuminate\Support\Carbon` types were wrong.

### Later

- Detect stages that stay `running` because their callback never arrived (worker killed) and
  offer "retry" (Phase 6 adds retry/cancel).
- Trigram index (`pg_trgm`) if title search gets slow.

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
      moves from Phase 6 to Phase 1. Defaults are `large-v3-turbo`, `cuda`, `int8_float16`, with `medium`
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
    - Reverb listens on 8081 (`REVERB_SERVER_PORT`/`VITE_REVERB_PORT`) because XAMPP Apache uses 8080.
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
