<?php

namespace App\Services\YouTube;

use App\Enums\FileRole;
use App\Models\Record;
use App\Models\RecordFile;
use Illuminate\Support\Facades\Storage;

/**
 * What the Publish panel starts from, and YouTube's limits (PROJECT_SPEC §6
 * `youtube` preset): title ≤ 100 characters, description ≤ 5000, tags ≤ 500
 * characters in total, no `<` or `>` in either; chapters only when the first
 * starts at 00:00, there are at least three and each lasts 10 s or more.
 * The title is the record's; description, tags, chapters and hashtags come
 * from the active AI metadata.
 */
class YouTubeDraft
{
    public const int TITLE_MAX = 100;

    public const int DESCRIPTION_MAX = 5000;

    public const int TAGS_MAX_TOTAL = 500;

    public const int THUMBNAIL_MAX_BYTES = 2 * 1024 * 1024;

    /**
     * YouTube video categories offered in the Publish panel (assignable in
     * most regions). YouTube sends and takes the ids as strings.
     *
     * @var array<int, string>
     */
    public const array CATEGORIES = [
        1 => 'Film & Animation',
        10 => 'Music',
        17 => 'Sports',
        19 => 'Travel & Events',
        22 => 'People & Blogs',
        24 => 'Entertainment',
        25 => 'News & Politics',
        26 => 'Howto & Style',
        27 => 'Education',
        28 => 'Science & Technology',
        29 => 'Nonprofits & Activism',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function categoryOptions(): array
    {
        $options = [];

        foreach (self::CATEGORIES as $id => $name) {
            $options[] = ['value' => (string) $id, 'label' => $name];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function categoryIds(): array
    {
        return array_column(self::categoryOptions(), 'value');
    }

    /**
     * Containers YouTube doesn't take; such a master is published from its
     * browser copy (proxy or access file) instead.
     */
    private const array UNSUPPORTED_MIME_TYPES = ['application/mxf'];

    /**
     * @return array{title: string, description: string, tags: list<string>}
     */
    public function defaults(Record $record): array
    {
        $payload = $record->activeMetadata()->value('payload');
        $ai = is_string($payload) ? (array) json_decode($payload, true) : (is_array($payload) ? $payload : []);

        $description = trim((string) ($ai['description'] ?? '')) ?: trim((string) $record->description);
        $chapters = $this->chapterLines(is_array($ai['chapters'] ?? null) ? $ai['chapters'] : []);
        $hashtags = array_values(array_filter(
            is_array($ai['hashtags'] ?? null) ? $ai['hashtags'] : [],
            fn (mixed $tag): bool => is_string($tag) && preg_match('/^#\S+$/u', $tag) === 1,
        ));

        $parts = array_filter([
            $description,
            $chapters === [] ? null : implode("\n", $chapters),
            $hashtags === [] ? null : implode(' ', array_slice($hashtags, 0, 15)),
        ]);

        $title = self::clean($record->title) ?: self::clean((string) ($ai['selectedTitle'] ?? '')) ?: 'Untitled video';

        return [
            'title' => mb_substr($title, 0, self::TITLE_MAX),
            'description' => mb_substr(self::clean(implode("\n\n", $parts), keepLines: true), 0, self::DESCRIPTION_MAX),
            'tags' => self::fitTags(is_array($ai['tags'] ?? null) ? $ai['tags'] : []),
        ];
    }

    /**
     * The file sent to YouTube: the master, the best copy, unless YouTube
     * can't read its container (MXF); then the browser copy.
     */
    public function sourceFile(Record $record): ?RecordFile
    {
        $files = $record->files()
            ->whereIn('role', [FileRole::Master, FileRole::Proxy, FileRole::Access])
            ->get(['id', 'record_id', 'role', 'storage_location', 'mime_type', 'file_size']);

        $master = $files->firstWhere('role', FileRole::Master);

        if ($master !== null && ! in_array($master->mime_type, self::UNSUPPORTED_MIME_TYPES, true)) {
            return $master;
        }

        return $files->firstWhere('role', FileRole::Proxy) ?? $files->firstWhere('role', FileRole::Access);
    }

    /**
     * Whether the record's thumbnail can be sent (JPEG or PNG, ≤ 2 MB).
     */
    public function thumbnailUsable(Record $record): bool
    {
        if ($record->thumbnail_path === null) {
            return false;
        }

        $disk = Storage::disk('media');

        return $disk->exists($record->thumbnail_path)
            && in_array($disk->mimeType($record->thumbnail_path), ['image/jpeg', 'image/png'], true)
            && $disk->size($record->thumbnail_path) <= self::THUMBNAIL_MAX_BYTES;
    }

    /**
     * Tags YouTube accepts: trimmed, no `<`/`>` or commas, unique, and within
     * the 500-character budget (a tag with a space counts its quotes too).
     *
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    public static function fitTags(array $tags): array
    {
        $kept = [];
        $total = 0;

        foreach ($tags as $tag) {
            $tag = trim(str_replace(['<', '>', ','], '', is_string($tag) ? $tag : ''));
            $tag = ltrim($tag, '#');

            if ($tag === '' || in_array(mb_strtolower($tag), array_map(mb_strtolower(...), $kept), true)) {
                continue;
            }

            $cost = self::tagLength($tag) + ($kept === [] ? 0 : 1);

            if ($total + $cost > self::TAGS_MAX_TOTAL) {
                break;
            }

            $kept[] = $tag;
            $total += $cost;
        }

        return $kept;
    }

    /**
     * How YouTube counts a tag toward the 500 characters.
     */
    public static function tagLength(string $tag): int
    {
        return mb_strlen($tag) + (str_contains($tag, ' ') ? 2 : 0);
    }

    /**
     * @param  list<string>  $tags
     */
    public static function tagsLength(array $tags): int
    {
        return array_sum(array_map(self::tagLength(...), $tags)) + max(0, count($tags) - 1);
    }

    /**
     * YouTube refuses `<` and `>` in titles and descriptions.
     */
    public static function clean(string $text, bool $keepLines = false): string
    {
        $text = str_replace(['<', '>'], '', $text);

        return $keepLines ? trim(preg_replace("/\r\n?/", "\n", $text) ?? '') : trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * "00:00 Intro" lines, only when YouTube would turn them into chapters.
     *
     * @param  array<mixed>  $chapters
     * @return list<string>
     */
    private function chapterLines(array $chapters): array
    {
        $valid = array_values(array_filter($chapters, fn (mixed $chapter): bool => is_array($chapter)
            && is_numeric($chapter['start'] ?? null)
            && is_string($chapter['title'] ?? null)
            && trim($chapter['title']) !== ''));
        usort($valid, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        if (count($valid) < 3 || (float) $valid[0]['start'] > 0.5) {
            return [];
        }

        foreach ($valid as $index => $chapter) {
            $next = $valid[$index + 1]['start'] ?? null;

            if ($next !== null && (float) $next - (float) $chapter['start'] < 10) {
                return [];
            }
        }

        $long = (float) end($valid)['start'] >= 3600;

        return array_map(function (array $chapter, int $index) use ($long): string {
            $seconds = $index === 0 ? 0 : (int) floor((float) $chapter['start']);
            $stamp = $long
                ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
                : sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);

            return $stamp.' '.self::clean($chapter['title']);
        }, $valid, array_keys($valid));
    }
}
