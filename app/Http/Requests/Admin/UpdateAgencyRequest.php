<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductionAgency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename an agency or unit. Moving a unit to another agency is not offered:
 * records already credit it under its agency.
 */
class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agency = $this->route('agency');

        return $agency instanceof ProductionAgency && ($this->user()?->can('update', $agency) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ProductionAgency $agency */
        $agency = $this->route('agency');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_agencies', 'name')
                    ->ignore($agency->id)
                    ->where(fn ($query) => $agency->parent_id === null
                        ? $query->whereNull('parent_id')
                        : $query->where('parent_id', $agency->parent_id)),
            ],
            'acronym' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'There is already one with this name here.'];
    }
}
