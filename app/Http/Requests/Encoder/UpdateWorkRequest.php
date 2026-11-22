<?php

namespace App\Http\Requests\Encoder;

use App\Models\Work;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rename a work or change its number of parts.
 */
class UpdateWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $work = $this->route('work');

        return $work instanceof Work && ($this->user()?->can('update', $work) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'total_parts' => ['nullable', 'integer', 'between:1,999'],
        ];
    }
}
