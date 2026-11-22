<?php

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A check of an added file (signed by the AI service): its checksums, its
 * probe summary, or why the check failed. Rules depend on the {stage}.
 */
class ReceiveFileResultRequest extends FormRequest
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
        return match ($this->route('stage')) {
            'checksum' => [
                'md5' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
                'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'file_size' => ['required', 'integer', 'min:0'],
            ],
            'probe' => (new ReceiveStageResultRequest)->probeRules(),
            default => ['message' => ['required', 'string', 'max:1000']],
        };
    }
}
