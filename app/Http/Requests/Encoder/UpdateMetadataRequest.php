<?php

namespace App\Http\Requests\Encoder;

use App\Http\Requests\Internal\ReceiveStageResultRequest;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The encoder's edits to a generation: same shape as the AI output, plus the chosen title.
 */
class UpdateMetadataRequest extends FormRequest
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
            ...ReceiveStageResultRequest::metadataRules(),
            'selectedTitle' => ['nullable', 'string', 'max:300'],
        ];
    }
}
