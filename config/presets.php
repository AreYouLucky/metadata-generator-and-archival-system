<?php

/*
|--------------------------------------------------------------------------
| Platform presets for AI metadata (PROJECT_SPEC §6)
|--------------------------------------------------------------------------
|
| The single source of truth for platform limits: sent to the AI service with
| every generation, and used by the workspace for character counters. Limits
| change; verify them before relying on them (last checked 2026-11-22, see
| DECISIONS.md). A null title_max means the platform has no title field; the
| titles are then working titles for the archive and are not cut.
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

    'tiktok' => [
        'label' => 'TikTok',
        'title_max' => null,
        'description_max' => 2200,
        'tags_max_count' => 10,
        'tags_max_chars' => null,
        'hashtags_min' => 3,
        'hashtags_max' => 5,
        'chapters' => false,
        'guidance' => 'A short caption: hook in the first line (about 80 characters show before "more"), casual and energetic, then the hashtags. Titles are short working titles.',
    ],

    'instagram' => [
        'label' => 'Instagram',
        'title_max' => null,
        'description_max' => 2200,
        'tags_max_count' => 10,
        'tags_max_chars' => null,
        'hashtags_min' => 3,
        'hashtags_max' => 5,
        'chapters' => false,
        'guidance' => 'A caption whose first line (about 125 characters show before "more") carries the point; warm and visual, short paragraphs. Titles are short working titles.',
    ],

    'linkedin' => [
        'label' => 'LinkedIn',
        'title_max' => null,
        'description_max' => 3000,
        'tags_max_count' => 10,
        'tags_max_chars' => null,
        'hashtags_min' => 3,
        'hashtags_max' => 5,
        'chapters' => false,
        'guidance' => 'Professional and informative: the first two lines (about 210 characters show before "see more") state the outcome or why it matters, then short paragraphs. Titles are short working titles.',
    ],

];
