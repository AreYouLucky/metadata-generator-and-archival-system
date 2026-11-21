<?php

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A translation moved forward or failed (signed by the AI service).
 */
class ReceiveTranslationProgressRequest extends FormRequest
{
    /**
     * The VerifyInternalSignature middleware has already authenticated the caller.
     */
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
            'status' => ['required', Rule::in(['running', 'failed'])],
            'progress' => ['required', 'integer', 'between:0,100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
