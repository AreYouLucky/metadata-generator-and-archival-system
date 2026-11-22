<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correct a key frame's description and/or on-screen text. Objects and the
 * face count come from the detectors and can't be edited.
 */
class UpdateRecordFrameRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ocr_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The fields sent, trimmed; an empty one becomes null.
     *
     * @return array{description?: string|null, ocr_text?: string|null}
     */
    public function changes(): array
    {
        return array_map(function (mixed $value): ?string {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        }, $this->validated());
    }
}
