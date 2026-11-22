<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Read one finished translation's lines (the route's {language} is validated
 * here like any other input).
 */
class ShowTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('view', $record) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['language' => $this->route('language')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/'],
        ];
    }
}
