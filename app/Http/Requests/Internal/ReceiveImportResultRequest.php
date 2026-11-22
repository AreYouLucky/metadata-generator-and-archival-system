<?php

namespace App\Http\Requests\Internal;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The outcome of a URL import (signed by the AI service): the downloaded
 * file, which must sit in this record's master folder under a ULID name, and
 * what the site said about the video; or the reason it failed.
 */
class ReceiveImportResultRequest extends FormRequest
{
    /**
     * The VerifyInternalSignature middleware has already authenticated the caller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $record = $this->route('record');
        $recordId = $record instanceof Record ? preg_quote($record->id, '/') : 'none';

        return [
            'status' => ['required', 'in:done,failed'],
            'error' => ['required_if:status,failed', 'nullable', 'string', 'max:1000'],
            'path' => ['required_if:status,done', 'nullable', 'string', 'max:255', "regex:/^records\\/{$recordId}\\/master\\/[0-9A-HJKMNP-TV-Z]{26}\\.[a-z0-9]{2,5}$/i"],
            'file_size' => ['required_if:status,done', 'nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'array'],
            'source.title' => ['nullable', 'string', 'max:500'],
            'source.uploader' => ['nullable', 'string', 'max:255'],
            'source.upload_date' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'source.extractor' => ['nullable', 'string', 'max:64'],
            'source.webpage_url' => ['nullable', 'string', 'max:2048'],
            'source.duration' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{title: string|null, uploader: string|null, upload_date: string|null, extractor: string|null, webpage_url: string|null}
     */
    public function source(): array
    {
        /** @var array<string, mixed> $source */
        $source = $this->validated('source') ?? [];

        return [
            'title' => self::text($source['title'] ?? null),
            'uploader' => self::text($source['uploader'] ?? null),
            'upload_date' => self::text($source['upload_date'] ?? null),
            'extractor' => self::text($source['extractor'] ?? null),
            'webpage_url' => self::text($source['webpage_url'] ?? null),
        ];
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
