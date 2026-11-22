<?php

namespace App\Services\Chat;

use App\Models\Record;
use App\Models\TranscriptChunk;
use App\Services\AiService\AiServiceClient;
use App\Services\Records\VisualsPresenter;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Finds the transcript passages about a question, within one record.
 *
 * Semantic: the question and the passages are embedded by the AI service
 * (passages only once, and again only when they change or the model does),
 * then compared by cosine similarity here, with a small bonus for passages
 * that contain the question's words (names, places). One record has at most
 * a few hundred passages, so this needs no vector index. When embeddings
 * can't be made (no API key, service down) it falls back to word matching,
 * and says so.
 */
class TranscriptSearch
{
    public const int MAX_RESULTS = 8;

    /** Below this cosine similarity a passage isn't about the question. */
    public const float MIN_SIMILARITY = 0.2;

    private const float KEYWORD_BONUS = 0.1;

    private const int EMBED_BATCH = 64;

    private const int PASSAGE_CHARS = 1500;

    /** Words that say nothing about the topic (English and Filipino). */
    private const array STOP_WORDS = [
        'the', 'and', 'what', 'did', 'does', 'they', 'say', 'said', 'about', 'for', 'with', 'this', 'that',
        'was', 'were', 'are', 'how', 'who', 'when', 'where', 'why', 'there', 'from', 'video', 'talk', 'mention',
        'ano', 'ang', 'mga', 'nila', 'niya', 'kay', 'para', 'yung', 'siya', 'sila', 'tungkol', 'sinabi',
    ];

    public function __construct(
        private TranscriptIndex $index,
        private AiServiceClient $client,
        private ChatUsageMeter $meter,
        #[Config('archive.embedding_dimensions')] private int $dimensions = 1536,
    ) {}

    /**
     * Null when the record has no transcript lines.
     *
     * @return array{method: string, passages: list<array{start: float, end: float, time: string, text: string, relevance: float}>}|null
     */
    public function search(Record $record, string $query, int $limit): ?array
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        // One refresh at a time per record (two questions could race to add passages).
        $lock = Cache::lock("records:{$record->id}:transcript-index", 120);
        $lock->block(60);

        try {
            if (! $this->index->refresh($record)) {
                return null;
            }

            $question = $this->embedQuestion($record, $query);

            if ($question !== null) {
                $this->embedPassages($record, $question['model']);
            }
        } finally {
            $lock->release();
        }

        $terms = self::terms($query);
        $candidates = [];

        $record->transcriptChunks()
            ->select(['id', 'record_id', 'start', 'end', 'text', 'embedding', 'embedding_model', 'embedding_dimensions'])
            ->lazyById(100)
            ->each(function (TranscriptChunk $chunk) use ($question, $terms, &$candidates): void {
                $keyword = self::keywordScore($chunk->text, $terms);
                $vector = $question === null || $chunk->embedding_model !== $question['model'] ? null : $chunk->vector();

                if ($question !== null && $vector !== null && count($vector) === count($question['vector'])) {
                    $similarity = self::cosine($question['vector'], $vector);
                    $score = $similarity + self::KEYWORD_BONUS * $keyword;
                    $relevant = $similarity >= self::MIN_SIMILARITY || $keyword >= 0.5;
                } else {
                    $score = $keyword;
                    $relevant = $keyword > 0;
                }

                if ($relevant) {
                    $candidates[] = [
                        'start' => $chunk->start,
                        'end' => $chunk->end,
                        'time' => VisualsPresenter::clock($chunk->start).'–'.VisualsPresenter::clock($chunk->end),
                        'text' => mb_strimwidth($chunk->text, 0, self::PASSAGE_CHARS, '…'),
                        'relevance' => round($score, 3),
                    ];
                }
            });

        usort($candidates, fn (array $first, array $second): int => $second['relevance'] <=> $first['relevance']);

        return [
            'method' => $question === null ? 'keyword' : 'semantic',
            'passages' => array_slice($candidates, 0, $limit),
        ];
    }

    /**
     * @return array{model: string, vector: list<float>}|null
     */
    private function embedQuestion(Record $record, string $query): ?array
    {
        $result = $this->embed($record, [$query]);
        $vector = $result['vectors'][0] ?? null;

        return $result === null || $vector === null ? null : ['model' => $result['model'], 'vector' => $vector];
    }

    /**
     * Embed passages that have no vector, or one from another model or size.
     */
    private function embedPassages(Record $record, string $model): void
    {
        $record->transcriptChunks()
            ->where(fn ($query) => $query->whereNull('embedding')
                ->orWhere('embedding_model', '!=', $model)
                ->orWhere('embedding_dimensions', '!=', $this->dimensions))
            ->select(['id', 'record_id', 'text'])
            ->chunkById(self::EMBED_BATCH, function ($chunks) use ($record): bool {
                $result = $this->embed($record, array_values($chunks->map(fn (TranscriptChunk $chunk): string => $chunk->text)->all()));

                if ($result === null) {
                    return false;
                }

                foreach ($chunks->values() as $position => $chunk) {
                    $chunk->forceFill([
                        'embedding' => TranscriptChunk::encodeVector($result['vectors'][$position]),
                        'embedding_model' => $result['model'],
                        'embedding_dimensions' => $this->dimensions,
                    ])->save();
                }

                return true;
            });
    }

    /**
     * @param  list<string>  $texts
     * @return array{model: string, vectors: list<list<float>>}|null
     */
    private function embed(Record $record, array $texts): ?array
    {
        try {
            $result = $this->client->embed($texts, $this->dimensions, $record->id);
        } catch (RequestException|ConnectionException $exception) {
            Log::info('Chat search fell back to word matching', ['record_id' => $record->id, 'error' => $exception->getMessage()]);

            return null;
        }

        $this->meter->add($result['usage'] ?? []);
        $vectors = $result['vectors'] ?? [];

        if (! isset($result['model']) || count($vectors) !== count($texts)) {
            return null;
        }

        return ['model' => $result['model'], 'vectors' => $vectors];
    }

    /**
     * @return list<string>
     */
    public static function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true),
        ))), 0, 10);
    }

    /**
     * Share of the question's words found in the passage (0–1).
     *
     * @param  list<string>  $terms
     */
    private static function keywordScore(string $text, array $terms): float
    {
        if ($terms === []) {
            return 0.0;
        }

        $found = count(array_filter($terms, fn (string $term): bool => mb_stripos($text, $term) !== false));

        return $found / count($terms);
    }

    /**
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    private static function cosine(array $first, array $second): float
    {
        $dot = 0.0;
        $firstNorm = 0.0;
        $secondNorm = 0.0;

        foreach ($first as $index => $value) {
            $other = $second[$index];
            $dot += $value * $other;
            $firstNorm += $value * $value;
            $secondNorm += $other * $other;
        }

        return $firstNorm > 0 && $secondNorm > 0 ? $dot / (sqrt($firstNorm) * sqrt($secondNorm)) : 0.0;
    }
}
