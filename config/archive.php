<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload and processing limits
    |--------------------------------------------------------------------------
    */

    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 2048),

    'max_video_minutes' => (int) env('MAX_VIDEO_MINUTES', 180),

    'max_batch_size' => (int) env('MAX_BATCH_SIZE', 20),
    // Release forms, contracts and sidecars (private disk only).
    'max_attachment_mb' => (int) env('MAX_ATTACHMENT_MB', 50),

    'encoder_daily_processing_minutes' => (int) env('ENCODER_DAILY_PROCESSING_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Archival settings
    |--------------------------------------------------------------------------
    |
    | The accession number format supports the {year} and {seq:N} tokens,
    | where N is the zero-padded width of the system-wide sequence.
    |
    */

    'accession_number_format' => env('ACCESSION_NUMBER_FORMAT', 'ACC-{year}-{seq:5}'),

    'fixity_check_interval_days' => (int) env('FIXITY_CHECK_INTERVAL_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Transcript translations
    |--------------------------------------------------------------------------
    |
    | Languages a transcript can be translated into (code => English name, the
    | name is what the AI service is told). Codes are ISO 639 / BCP 47, so they
    | also work as the `srclang` of the player's caption tracks.
    |
    */

    'translation_languages' => [
        'en' => 'English',
        'fil' => 'Filipino',
        'ceb' => 'Cebuano',
        'ilo' => 'Ilocano',
        'hil' => 'Hiligaynon',
        'war' => 'Waray',
        'bcl' => 'Central Bikol',
        'pam' => 'Kapampangan',
        'pag' => 'Pangasinan',
        'es' => 'Spanish',
        'zh-Hans' => 'Chinese (Simplified)',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'fr' => 'French',
        'de' => 'German',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Must match the output size of the embedding model used by the AI service.
    |
    */

    'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Clips (Phase 7)
    |--------------------------------------------------------------------------
    |
    | Parts of a record cut to their own MP4 (H.264/AAC, never taller than
    | max_height). Each is re-encoded, so length is limited.
    |
    */

    'clips' => [
        'min_seconds' => (float) env('CLIP_MIN_SECONDS', 1),
        'max_seconds' => (float) env('CLIP_MAX_SECONDS', 600),
        'max_per_record' => (int) env('CLIP_MAX_PER_RECORD', 100),
        'max_height' => (int) env('CLIP_MAX_HEIGHT', 1080),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API (Phase 7)
    |--------------------------------------------------------------------------
    |
    | Sanctum tokens under /api/v1. Requests are limited per token. A file
    | sent straight to POST /api/v1/records is limited by max_upload_mb here
    | and by PHP's upload_max_filesize / post_max_size; bigger videos can be
    | imported from a web address instead. Webhooks are POSTed once
    | processing finishes, signed with the owner's webhook secret.
    |
    */

    'api' => [
        'requests_per_minute' => (int) env('API_REQUESTS_PER_MINUTE', 60),
        'max_upload_mb' => (int) env('API_MAX_UPLOAD_MB', 512),
        'per_page' => 25,
        'max_per_page' => 100,
        'webhook_timeout_seconds' => (int) env('API_WEBHOOK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube publishing (Phase 7)
    |--------------------------------------------------------------------------
    |
    | Uploads are resumable and sent in chunks (a multiple of 256 KiB); each
    | queued step uploads for at most step_seconds, then queues the next, so
    | no job runs longer than the queue's timeout. Once uploaded, YouTube's
    | processing status is checked every status_check_seconds, up to
    | status_checks times. Category 22 is "People & Blogs".
    |
    */

    'youtube' => [
        'chunk_mb' => (int) env('YOUTUBE_CHUNK_MB', 8),
        'step_seconds' => (int) env('YOUTUBE_STEP_SECONDS', 40),
        'default_category_id' => (string) env('YOUTUBE_DEFAULT_CATEGORY', '22'),
        'status_check_seconds' => (int) env('YOUTUBE_STATUS_CHECK_SECONDS', 60),
        'status_checks' => (int) env('YOUTUBE_STATUS_CHECKS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public catalogue
    |--------------------------------------------------------------------------
    */

    'public_catalogue_requires_login' => (bool) env('PUBLIC_CATALOGUE_REQUIRES_LOGIN', false),

    'allow_public_registration' => (bool) env('ALLOW_PUBLIC_REGISTRATION', true),

    /*
    |--------------------------------------------------------------------------
    | AI service and signed internal calls
    |--------------------------------------------------------------------------
    |
    | Every request between Laravel and the Python service, in both directions,
    | carries `X-Timestamp` and `X-Signature: sha256=HMAC(secret, ts.body)`.
    | Requests older (or further in the future) than the max age are rejected,
    | and each signature is accepted only once inside that window.
    |
    */

    'ai_service' => [
        'url' => rtrim((string) env('AI_SERVICE_URL', 'http://ai-api:8000'), '/'),
        'timeout_seconds' => (int) env('AI_SERVICE_TIMEOUT', 10),
    ],

    'internal' => [
        'shared_secret' => env('INTERNAL_SHARED_SECRET'),
        'signature_max_age_seconds' => (int) env('INTERNAL_SIGNATURE_MAX_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | First admin account (UserSeeder)
    |--------------------------------------------------------------------------
    |
    | Outside local/testing the seeder refuses to run without ADMIN_PASSWORD,
    | so a production database never gets a known default password.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'System Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
