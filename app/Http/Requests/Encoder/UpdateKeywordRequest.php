<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use App\Models\RecordKeyword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirm a suggested keyword, or correct it.
 */
class UpdateKeywordRequest extends FormRequest
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
        $keyword = $this->route('keyword');

        return [
            'keyword' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('record_keywords', 'keyword')
                    ->where('record_id', $record instanceof Record ? $record->id : null)
                    ->ignore($keyword instanceof RecordKeyword ? $keyword->id : null),
            ],
            'is_confirmed' => ['sometimes', 'required', 'boolean'],
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
