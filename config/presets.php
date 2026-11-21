<?php

/*
|--------------------------------------------------------------------------
| Platform presets for AI metadata (PROJECT_SPEC §6)
|--------------------------------------------------------------------------
|
| The single source of truth for platform limits: sent to the AI service with
| every generation, and used by the workspace for character counters. Limits
| change; verify them before relying on them. Phase 1 ships `generic` and
| `youtube`; the other presets arrive in Phase 2.
|
*/

return [

    'generic' => [
        'label' => 'Generic',
        'title_max' => 100,
        'description_max' => 2000,
        'tags_max_count' => 15,
        'tags_max_chars' => null,
        'hashtags_min' => 0,
        'hashtags_max' => 0,
        'chapters' => true,
        'guidance' => 'Neutral, descriptive and archive-friendly.',
    ],

    'youtube' => [
        'label' => 'YouTube',
        'title_max' => 100,
        'description_max' => 5000,
        'tags_max_count' => 15,
        'tags_max_chars' => 500,
        'hashtags_min' => 0,
        'hashtags_max' => 3,
        'chapters' => true,
        'guidance' => 'Clear and searchable; the first two lines of the description matter most. Chapters: first at 00:00, at least 3, each at least 10 seconds.',
    ],

];
