<?php

namespace App\Http\Requests\Encoder;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('addSeries', $project) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreProjectRequest::fields($this);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreProjectRequest::fieldMessages();
    }
}
