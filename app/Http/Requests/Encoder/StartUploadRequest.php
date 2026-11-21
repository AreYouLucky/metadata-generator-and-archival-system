<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starts a chunked upload. The extension is only a first filter; the stored
 * file's real type is checked when the record is created.
 */
class StartUploadRequest extends FormRequest
{
    public const array EXTENSIONS = ['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mxf', 'mts', 'm2ts', 'mpg', 'mpeg', 'wmv', 'ts', '3gp'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', Record::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxBytes = config('archive.max_upload_mb') * 1024 * 1024;

        return [
            'filename' => ['required', 'string', 'max:255', 'regex:/\.('.implode('|', self::EXTENSIONS).')$/i'],
            'size' => ['required', 'integer', 'min:1', "max:{$maxBytes}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filename.regex' => 'Choose a video file ('.implode(', ', self::EXTENSIONS).').',
            'size.max' => 'The file is larger than the '.config('archive.max_upload_mb').' MB limit.',
        ];
    }

    /**
     * The file name without any path, control characters or odd whitespace.
     */
    public function safeFilename(): string
    {
        $name = basename(str_replace('\\', '/', (string) $this->validated('filename')));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->safeFilename(), PATHINFO_EXTENSION));
    }
}
