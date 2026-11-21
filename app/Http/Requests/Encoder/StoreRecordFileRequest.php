<?php

namespace App\Http\Requests\Encoder;

use App\Enums\FileRole;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add an extra file to a record (PROJECT_SPEC §8, `record_files`): a
 * mezzanine or an access copy, sent first through the chunked upload. The
 * proxy is made by the pipeline, never uploaded.
 */
class StoreRecordFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'uuid'],
            'role' => ['required', Rule::in([FileRole::Mezzanine->value, FileRole::Access->value])],
            'expected_md5' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{32}$/'],
            'expected_sha256' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_md5.regex' => 'An MD5 checksum is 32 hexadecimal characters.',
            'expected_sha256.regex' => 'A SHA-256 checksum is 64 hexadecimal characters.',
        ];
    }
}
