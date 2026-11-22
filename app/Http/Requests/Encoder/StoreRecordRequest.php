<?php

namespace App\Http\Requests\Encoder;

use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Http\Requests\Concerns\HasRecordDetailRules;
use App\Models\Record;
use App\Rules\PublicMediaUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One new record: a finished chunked upload (`upload_id`) or a web address
 * to import (`url`, Phase 6), never both.
 */
class StoreRecordRequest extends FormRequest
{
    use ChecksDailyProcessingLimit, HasRecordDetailRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Record::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required_without:url', 'prohibits:url', 'nullable', 'uuid'],
            'url' => ['required_without:upload_id', 'nullable', 'string', new PublicMediaUrl],
            ...$this->recordDetailRules(),
            'title' => ['nullable', 'string', 'max:250'],
            // A manifest's checksums only make sense for a file the encoder has.
            'expected_md5' => ['nullable', 'prohibits:url', 'string', 'regex:/^[A-Fa-f0-9]{32}$/'],
            'expected_sha256' => ['nullable', 'prohibits:url', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->recordDetailMessages(),
            'upload_id.required_without' => 'Choose a video file or enter a web address.',
            'url.required_without' => 'Choose a video file or enter a web address.',
            'upload_id.prohibits' => 'Upload a file or import a web address, not both.',
            'expected_md5.prohibits' => 'Expected checksums apply to uploaded files only.',
            'expected_sha256.prohibits' => 'Expected checksums apply to uploaded files only.',
            'expected_md5.regex' => 'An MD5 checksum is 32 hexadecimal characters.',
            'expected_sha256.regex' => 'A SHA-256 checksum is 64 hexadecimal characters.',
        ];
    }
}
