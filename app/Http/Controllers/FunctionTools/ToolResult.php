<?php

namespace App\Http\Controllers\FunctionTools;

/**
 * What a function tool returns to the chat model: its data, whether the
 * archive actually had that data, and the moments of which record it covers
 * (citations in the answer must fall on one of them).
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{record_id?: string, start: float, end: float}>  $moments
     */
    private function __construct(
        public bool $available,
        public array $data,
        public array $moments = [],
    ) {}

    /**
     * The archive has this data. Moments without a record id get one from
     * RecordTool; search results set their own.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{record_id?: string, start: float, end: float}>  $moments
     */
    public static function found(array $data, array $moments = []): self
    {
        return new self(true, $data, $moments);
    }

    /**
     * The record doesn't have this data, or the account may not see it;
     * `reason` tells the model why, so it can say so instead of guessing.
     *
     * @param  array<string, mixed>  $data
     */
    public static function unavailable(string $reason, array $data = []): self
    {
        return new self(false, ['reason' => $reason, ...$data]);
    }

    /**
     * The call itself was wrong (unknown tool, bad arguments, unknown record).
     */
    public static function error(string $message): self
    {
        return new self(false, ['error' => $message]);
    }

    /**
     * The same result with every moment tagged with the record.
     */
    public function forRecord(string $recordId): self
    {
        return new self($this->available, $this->data, array_map(
            fn (array $moment): array => ['record_id' => $moment['record_id'] ?? $recordId, 'start' => $moment['start'], 'end' => $moment['end']],
            $this->moments,
        ));
    }

    /**
     * The JSON the model reads.
     */
    public function toJson(): string
    {
        return json_encode(
            ['available' => $this->available, ...$this->data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?: '{"available":false,"error":"The result could not be encoded."}';
    }
}
