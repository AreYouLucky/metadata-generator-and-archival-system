<?php

namespace App\Http\Requests\Encoder;

use App\Enums\ContributorRole;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncContributorsRequest extends FormRequest
{
    public const MAX_CONTRIBUTORS = 100;

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * The whole credits list in display order. Rows with an id update that credit
     * (it must belong to this record); rows without one are new; missing ids are removed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');

        return [
            'contributors' => ['present', 'array', 'max:'.self::MAX_CONTRIBUTORS],
            'contributors.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('record_contributors', 'id')->where('record_id', $record->id),
            ],
            'contributors.*.role' => ['required', Rule::enum(ContributorRole::class)],
            'contributors.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contributors.*.name.required' => 'Every credit needs a name.',
        ];
    }
}
