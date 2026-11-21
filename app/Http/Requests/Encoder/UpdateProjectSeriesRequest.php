<?php

namespace App\Http\Requests\Encoder;

use App\Models\ProjectSeries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a series: allowed to whoever may edit its project.
 */
class UpdateProjectSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $series = $this->route('series');

        // A deleted project's series resolve to no project and stay locked.
        return $series instanceof ProjectSeries
            && $series->project !== null
            && ($this->user()?->can('update', $series->project) ?? false);
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
