<?php

namespace App\Http\Requests\Encoder;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Enums\ReleaseConsent;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The record's rights and access metadata (PROJECT_SPEC §8, `record_rights`).
 */
class SaveRightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'copyright_holder' => ['nullable', 'string', 'max:250'],
            'copyright_year' => ['nullable', 'integer', 'between:1800,'.(now()->year + 1)],
            'rights_statement' => ['nullable', 'string', 'max:5000'],
            'license' => ['nullable', 'string', 'max:250'],
            'license_url' => ['nullable', 'url:http,https', 'max:250'],
            'access_level' => ['required', Rule::enum(AccessLevel::class)],
            'usage_restrictions' => ['nullable', 'string', 'max:5000'],
            // ISO 3166-1 alpha-2 codes, e.g. PH.
            'territories' => ['present', 'array', 'max:60'],
            'territories.*' => ['string', 'distinct', 'regex:/^[A-Z]{2}$/'],
            'embargo_until' => ['nullable', 'date'],
            'permission_status' => ['required', Rule::enum(PermissionStatus::class)],
            'release_consent' => ['required', Rule::enum(ReleaseConsent::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'territories.*.regex' => 'Use two-letter country codes, e.g. PH.',
            'license_url.url' => 'Enter a full http or https link.',
        ];
    }
}
