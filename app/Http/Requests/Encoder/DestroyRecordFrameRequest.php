<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remove a key frame (and its image). Nothing to validate beyond the route,
 * which only binds frames of this record.
 */
class DestroyRecordFrameRequest extends FormRequest
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
        return [];
    }
}
