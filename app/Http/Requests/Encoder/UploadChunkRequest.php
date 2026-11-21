<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadChunkRequest extends FormRequest
{
    /**
     * The browser sends 8 MB chunks; PHP accepts up to post_max_size (40 MB on XAMPP).
     */
    public const int MAX_CHUNK_KB = 16 * 1024;

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
            'offset' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.self::MAX_CHUNK_KB],
        ];
    }
}
