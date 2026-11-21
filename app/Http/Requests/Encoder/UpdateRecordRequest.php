<?php

namespace App\Http\Requests\Encoder;

use App\Enums\ArchivalValue;
use App\Enums\DatePrecision;
use App\Enums\DispositionAction;
use App\Enums\MetadataSource;
use App\Enums\SecurityClassification;
use App\Models\Record;
use App\Support\LocationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /records/{record}: the catalogue fields of the Archive panel. Each
 * sub-tab sends only its own group of fields (every rule is `sometimes`), and
 * a group is always sent whole: project with series, agency with unit, and
 * the whole location chain, since each level is checked against the one above.
 */
class UpdateRecordRequest extends FormRequest
{
    /**
     * Location fields; when any is sent, the whole chain is validated.
     */
    private const array LOCATION_FIELDS = ['region_id', 'prov_id', 'city_id', 'brgy_id'];

    public function authorize(): bool
    {
        return $this->record() !== null && ($this->user()?->can('update', $this->record()) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $record = $this->record();
        $user = $this->user();

        return [
            // Descriptive
            'title' => ['sometimes', 'required', 'string', 'max:250'],
            'alternative_title' => ['sometimes', 'nullable', 'string', 'max:250'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'genre_id' => ['sometimes', 'nullable', 'integer', Rule::exists('genres', 'id')],
            'video_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('video_types', 'id')],
            'language' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/'],
            'country_origin' => ['sometimes', 'nullable', 'string', 'max:64'],
            'date_recorded' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            // Not `sometimes`: required_with must also fire when the field is left out.
            'date_recorded_precision' => ['nullable', 'required_with:date_recorded', Rule::enum(DatePrecision::class)],
            'date_recorded_source' => ['sometimes', 'nullable', Rule::in([MetadataSource::Manual->value, MetadataSource::ContainerTag->value, MetadataSource::Ai->value])],
            'date_published' => ['sometimes', 'nullable', 'date'],
            'season_number' => ['sometimes', 'nullable', 'integer', 'between:1,9999'],
            'episode_number' => ['sometimes', 'nullable', 'integer', 'between:1,9999'],
            'project_id' => ['sometimes', 'required', 'string', Rule::exists('projects', 'id')->withoutTrashed()],
            'project_series_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('project_series', 'id')->where('project_id', (string) $this->input('project_id', $record?->project_id)),
            ],

            // Location (GPS is optional; both coordinates or neither)
            'location' => ['sometimes', 'nullable', 'string', 'max:250'],
            ...($this->hasAny(self::LOCATION_FIELDS) ? LocationRules::rules($this) : []),
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_source' => ['sometimes', 'nullable', Rule::in([MetadataSource::Manual->value, MetadataSource::Ai->value, MetadataSource::ContainerGps->value])],

            // Credits
            'agency_id' => ['sometimes', 'nullable', 'integer', 'required_with:division_id', Rule::exists('production_agencies', 'id')->whereNull('parent_id')],
            'division_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('production_agencies', 'id')->where('parent_id', (int) $this->input('agency_id', $record?->agency_id)),
            ],

            // Archival
            'accession_number' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('records', 'accession_number')->ignore($record?->id)],
            'security_classification' => ['sometimes', 'required', Rule::enum(SecurityClassification::class)],
            'retention_period' => ['sometimes', 'nullable', 'string', 'max:100'],
            'archival_value' => ['sometimes', 'nullable', Rule::enum(ArchivalValue::class)],
            'disposition_date' => ['sometimes', 'nullable', 'date'],
            'disposition_action' => ['nullable', 'required_with:disposition_date', Rule::enum(DispositionAction::class)],

            // Structure: a part of one of the user's works (admins: any work)
            'work_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('works', 'id')->when(! ($user?->isAdmin() ?? false), fn ($rule) => $rule->where('created_by', $user?->id)),
            ],
            'part_number' => ['sometimes', 'nullable', 'integer', 'between:1,999'],
            'part_label' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...LocationRules::messages(),
            'project_series_id.exists' => 'That series does not belong to the chosen project.',
            'agency_id.required_with' => 'Choose the agency of that production unit.',
            'division_id.exists' => 'That production unit does not belong to the chosen agency.',
            'accession_number.unique' => 'Another record already has that accession number.',
            'accession_number.regex' => 'Use letters, digits and . _ / - only.',
            'date_recorded.before_or_equal' => 'The recording date cannot be in the future.',
            'date_recorded_precision.required_with' => 'Say how exact the recording date is.',
            'disposition_action.required_with' => 'Choose what happens on the disposition date.',
            'work_id.exists' => 'Choose one of your works.',
        ];
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
