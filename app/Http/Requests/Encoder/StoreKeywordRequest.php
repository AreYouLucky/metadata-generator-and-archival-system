<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A keyword an encoder adds by hand (it is confirmed at once).
 */
class StoreKeywordRequest extends FormRequest
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
        $record = $this->route('record');

        return [
            'keyword' => [
                'required',
                'string',
                'max:100',
                Rule::unique('record_keywords', 'keyword')->where('record_id', $record instanceof Record ? $record->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['keyword.unique' => 'That keyword is already on this record.'];
    }
}
