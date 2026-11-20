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

];
