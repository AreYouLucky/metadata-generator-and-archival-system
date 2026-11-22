<?php

namespace App\Http\Requests\Encoder;

use App\Models\Work;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new work that groups reels or parts (each part is its own record).
 */
class StoreWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Work::class) ?? false;
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
