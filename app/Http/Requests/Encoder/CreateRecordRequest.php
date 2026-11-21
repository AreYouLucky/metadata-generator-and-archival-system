<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Import media page; `?project=` preselects a project (from "Upload to
 * this project"), and `?series=` also preselects one of its series.
 */
class CreateRecordRequest extends FormRequest
{
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
            'project' => ['nullable', 'string', Rule::exists('projects', 'id')->withoutTrashed()],
            // `?series=` (from a series row) must belong to that project.
            'series' => ['nullable', 'string', Rule::exists('project_series', 'id')->where('project_id', (string) $this->query('project'))],
        ];
    }
}
