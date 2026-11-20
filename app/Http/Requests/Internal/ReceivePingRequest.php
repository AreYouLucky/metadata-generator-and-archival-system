<?php

namespace App\Http\Requests\Internal;

use App\Enums\PingSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePingRequest extends FormRequest
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
            'ping_id' => ['required', 'uuid'],
            'source' => ['required', Rule::in([PingSource::AiWorker->value])],
        ];
    }
}
