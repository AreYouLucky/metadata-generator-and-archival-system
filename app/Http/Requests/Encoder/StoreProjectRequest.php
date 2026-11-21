<?php

namespace App\Http\Requests\Encoder;

use App\Models\Project;
use App\Support\LocationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A project (event or programme): name, date, description, venue text and
 * the region → province → city → barangay chain. The agency arrives in Phase 3.
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...self::fields($this), 'thumbnail' => self::thumbnailRules()];
    }

    /**
     * A cover image for the project (also used by the public catalogue later).
     *
     * @return array<int, string>
     */
    public static function thumbnailRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::fieldMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldMessages(): array
    {
        return [
            ...LocationRules::messages(),
            'date.required_with' => 'Set the first day as well.',
            'end_date.after_or_equal' => 'The last day cannot be before the first day.',
            'agency_id.exists' => 'Choose an agency from the list.',
            'thumbnail.max' => 'The thumbnail must be 5 MB or smaller.',
            'thumbnail.mimes' => 'Use a JPG, PNG or WebP image for the thumbnail.',
        ];
    }

    /**
     * Shared with series, which have the same fields.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fields(FormRequest $request): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // An event can run for several days: `date` is the first, `end_date` the last.
            'date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            // The producing agency (top level; units are chosen per record).
            'agency_id' => ['nullable', 'integer', Rule::exists('production_agencies', 'id')->whereNull('parent_id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            ...LocationRules::rules($request),
        ];
    }
}
