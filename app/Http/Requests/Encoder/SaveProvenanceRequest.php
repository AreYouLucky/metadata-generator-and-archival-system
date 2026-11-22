<?php

namespace App\Http\Requests\Encoder;

use App\Enums\SourceCarrier;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Where the file came from: source carrier and transfer details (`record_provenance`).
 */
class SaveProvenanceRequest extends FormRequest
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
            'source_carrier' => ['nullable', Rule::enum(SourceCarrier::class)],
            'carrier_label' => ['nullable', 'string', 'max:250'],
            'carrier_id' => ['nullable', 'string', 'max:250'],
            'transfer_date' => ['nullable', 'date', 'before_or_equal:today'],
            'transfer_facility' => ['nullable', 'string', 'max:250'],
            'transfer_operator' => ['nullable', 'string', 'max:250'],
            'transfer_deck' => ['nullable', 'string', 'max:250'],
            'transfer_settings' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
