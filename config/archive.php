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
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Must match the output size of the embedding model used by the AI service.
    |
    */

    'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),

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
