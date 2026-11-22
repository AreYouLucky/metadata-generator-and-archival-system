<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApiTokenAbility;
use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Http\Requests\Concerns\HasRecordDetailRules;
use App\Http\Requests\Encoder\StartUploadRequest;
use App\Models\Record;
use App\Models\User;
use App\Rules\PublicMediaUrl;
use App\Rules\WebhookUrl;
use App\Services\Records\PipelineOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * POST /api/v1/records: a video sent as multipart `file`, or a web address
 * to import (`url`), never both; filed and processed with the same options
 * as the Import page (`preset` defaults to the first enabled one). An
 * optional https `webhook_url` is called once processing finishes; it needs
 * a webhook secret (Settings → API tokens) to sign the call.
 */
class StoreRecordRequest extends FormRequest
{
    use ChecksDailyProcessingLimit {
        after as dailyLimitChecks;
    }
    use HasRecordDetailRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tokenCan(ApiTokenAbility::CreateRecords->value)
            && $user->can('create', Record::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('preset')) {
            $this->merge(['preset' => PipelineOptions::enabledPresets()[0] ?? null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The name is only a first filter; the stored file's real type is checked on ingest.
            'file' => ['required_without:url', 'prohibits:url', 'nullable', 'file', 'extensions:'.implode(',', StartUploadRequest::EXTENSIONS), 'max:'.((int) config('archive.api.max_upload_mb') * 1024)],
            'url' => ['required_without:file', 'nullable', 'string', new PublicMediaUrl],
            ...$this->recordDetailRules(),
            'title' => ['nullable', 'string', 'max:250'],
            'expected_md5' => ['nullable', 'prohibits:url', 'string', 'regex:/^[A-Fa-f0-9]{32}$/'],
            'expected_sha256' => ['nullable', 'prohibits:url', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'webhook_url' => ['nullable', 'string', new WebhookUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->recordDetailMessages(),
            'file.required_without' => 'Send a video file or a web address (url).',
            'url.required_without' => 'Send a video file or a web address (url).',
            'file.prohibits' => 'Send a file or a web address, not both.',
            'file.extensions' => 'Send a video file ('.implode(', ', StartUploadRequest::EXTENSIONS).').',
            'file.max' => 'Files sent through the API can be at most '.config('archive.api.max_upload_mb').' MB; import bigger videos from a web address.',
            'expected_md5.prohibits' => 'Expected checksums apply to uploaded files only.',
            'expected_sha256.prohibits' => 'Expected checksums apply to uploaded files only.',
            'expected_md5.regex' => 'An MD5 checksum is 32 hexadecimal characters.',
            'expected_sha256.regex' => 'A SHA-256 checksum is 64 hexadecimal characters.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            ...$this->dailyLimitChecks(),
            function (Validator $validator): void {
                $user = $this->user();

                if ($this->filled('webhook_url') && $user instanceof User && $user->webhook_secret === null) {
                    $validator->errors()->add('webhook_url', 'Create a webhook signing secret in Settings → API tokens first.');
                }
            },
        ];
    }

    public function videoFile(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * The client's file name without any path, control characters or odd whitespace.
     */
    public function safeFilename(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $name)), 0, 255);

        return $name !== '' ? $name : 'video.'.strtolower($file->getClientOriginalExtension());
    }
}
