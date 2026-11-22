<?php

namespace App\Http\Requests\Encoder;

use App\Exporters\ExportSchemaFields;
use App\Models\ExportSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A custom JSON export schema: a name and the dot-paths to keep, each one
 * from the known list (App\Exporters\ExportSchemaFields).
 */
class StoreExportSchemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExportSchema::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::schemaRules($this->user()?->id, null);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function schemaRules(?int $userId, ?int $ignoreId): array
    {
        $paths = ExportSchemaFields::paths();

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('export_schemas', 'name')->where('user_id', $userId)->ignore($ignoreId),
            ],
            'fields' => ['required', 'array', 'min:1', 'max:'.count($paths)],
            'fields.*' => ['required', 'string', 'distinct', Rule::in($paths)],
            'is_default' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a schema with that name.',
            'fields.required' => 'Pick at least one field.',
            'fields.min' => 'Pick at least one field.',
        ];
    }
}
