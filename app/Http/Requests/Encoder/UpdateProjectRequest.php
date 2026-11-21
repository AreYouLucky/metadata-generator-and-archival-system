<?php

namespace App\Http\Requests\Encoder;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a project. Sent as multipart (POST with `_method=PATCH`) because it may
 * carry a new thumbnail.
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...StoreProjectRequest::fields($this),
            'thumbnail' => StoreProjectRequest::thumbnailRules(),
            'remove_thumbnail' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreProjectRequest::fieldMessages();
    }
}
