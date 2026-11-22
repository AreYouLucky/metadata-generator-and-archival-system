<?php

namespace App\Http\Requests\Admin;

use App\Models\VideoType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a video type to the admin-managed list. The slug is generated.
 */
class StoreVideoTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VideoType::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('video_types', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already a video type with this name.'];
    }
}
