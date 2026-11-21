<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductionAgency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An agency, or (with parent_id) one of its production units. Units are one
 * level deep: a unit's parent must be a top-level agency.
 */
class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductionAgency::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $parentId = $this->integer('parent_id') ?: null;

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('production_agencies', 'id')->whereNull('parent_id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_agencies', 'name')->where(fn ($query) => $parentId === null
                    ? $query->whereNull('parent_id')
                    : $query->where('parent_id', $parentId)),
            ],
            'acronym' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'There is already one with this name here.',
            'parent_id.exists' => 'Units can only be added to a top-level agency.',
        ];
    }
}
