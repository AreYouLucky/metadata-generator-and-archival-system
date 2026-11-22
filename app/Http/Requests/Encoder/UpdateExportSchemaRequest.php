<?php

namespace App\Http\Requests\Encoder;

use App\Models\ExportSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExportSchemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schema = $this->route('exportSchema');

        return $schema instanceof ExportSchema && ($this->user()?->can('update', $schema) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schema = $this->route('exportSchema');

        return StoreExportSchemaRequest::schemaRules(
            $this->user()?->id,
            $schema instanceof ExportSchema ? $schema->id : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreExportSchemaRequest)->messages();
    }
}
