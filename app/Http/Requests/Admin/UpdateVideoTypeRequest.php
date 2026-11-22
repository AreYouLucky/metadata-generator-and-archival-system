<?php

namespace App\Http\Requests\Admin;

use App\Models\VideoType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename a video type or change its position in the list.
 */
class UpdateVideoTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $videoType = $this->route('videoType');

        return $videoType instanceof VideoType && ($this->user()?->can('update', $videoType) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var VideoType $videoType */
        $videoType = $this->route('videoType');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('video_types', 'name')->ignore($videoType->id)],
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
