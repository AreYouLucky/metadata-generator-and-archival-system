<?php

namespace App\Http\Requests\Concerns;

use App\Services\Records\PipelineOptions;
use Illuminate\Validation\Rule;

/**
 * What a new record is filed under and how it is processed: shared by a
 * single upload or URL import (POST /records) and a batch (POST /batches),
 * where the choices apply to every item.
 */
trait HasRecordDetailRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function recordDetailRules(): array
    {
        return [
            // Every record is filed under a project (event); the series is optional
            // but must belong to that project.
            'project_id' => ['required', 'string', Rule::exists('projects', 'id')->withoutTrashed()],
            'project_series_id' => [
                'nullable',
                'string',
                Rule::exists('project_series', 'id')->where('project_id', (string) $this->input('project_id')),
            ],
            // Producing agency and, optionally, one of its production units.
            'agency_id' => ['nullable', 'integer', 'required_with:division_id', Rule::exists('production_agencies', 'id')->whereNull('parent_id')],
            'division_id' => [
                'nullable',
                'integer',
                Rule::exists('production_agencies', 'id')->where('parent_id', (int) $this->input('agency_id')),
            ],
            'preset' => ['required', Rule::in(PipelineOptions::enabledPresets())],
            'custom_prompt' => ['nullable', 'string', 'max:2000'],
            ...PipelineOptions::processingRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function recordDetailMessages(): array
    {
        return [
            'project_id.required' => 'Choose the project (event) this video belongs to.',
            'project_series_id.exists' => 'That series does not belong to the chosen project.',
            'agency_id.required_with' => 'Choose the agency of that production unit.',
            'division_id.exists' => 'That production unit does not belong to the chosen agency.',
        ];
    }

    public function pipelineOptions(): PipelineOptions
    {
        return PipelineOptions::fromInput($this->validated());
    }

    /**
     * Where the new record is filed (project, series, agency, unit).
     *
     * @return array{project_id?: string|null, project_series_id?: string|null, agency_id?: int|null, division_id?: int|null}
     */
    public function filing(): array
    {
        /** @var array{project_id?: string|null, project_series_id?: string|null, agency_id?: int|null, division_id?: int|null} */
        return $this->safe()->only(['project_id', 'project_series_id', 'agency_id', 'division_id']);
    }
}
