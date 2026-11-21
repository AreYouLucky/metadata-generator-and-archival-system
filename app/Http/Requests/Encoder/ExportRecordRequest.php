<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportRecordRequest extends FormRequest
{
    /**
     * Phase 1 exports JSON; SRT, VTT, TXT, CSV and Markdown arrive in Phase 2.
     */
    public const array FORMATS = ['json'];

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('export', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(self::FORMATS)],
        ];
    }
}
