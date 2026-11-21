<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Generate more" title options for the record's active generation. Costs AI
 * tokens, so it needs the same right as a regeneration.
 */
class GenerateTitlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('generate', $record) ?? false);
    }

    /**
     * The active generation is named so a regeneration in between can't
     * receive titles written for the generation the encoder was looking at.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'metadata_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
