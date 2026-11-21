<?php

namespace App\Http\Requests\Encoder;

use App\Enums\AttachmentKind;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * A release form, contract, sidecar or document for the record. Stored on the
 * private disk under a generated name; the type is checked from the content.
 */
class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * Documents, images, subtitles and sidecar formats; never executables or HTML.
     */
    public const array EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'xml', 'json', 'srt', 'vtt', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', File::types(self::EXTENSIONS)->max(((int) config('archive.max_attachment_mb')) * 1024)],
            'kind' => ['required', Rule::enum(AttachmentKind::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
