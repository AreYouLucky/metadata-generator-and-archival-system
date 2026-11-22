<?php

namespace App\Http\Requests\Internal;

use App\Enums\FixityTrigger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A fixity check's checksums (signed by the AI service), or why it failed.
 * Laravel compares them with the ingest checksums; the service never decides.
 */
class ReceiveFixityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'triggered_by' => ['required', Rule::in([FixityTrigger::Schedule->value, FixityTrigger::User->value])],
            'md5' => ['required_without:error', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'sha256' => ['required_without:error', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'error' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
