<?php

namespace App\Http\Requests\Internal;

use App\Models\Clip;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A clip was cut ({path, file_size, md5, sha256, probe}) or failed
 * ({error}), signed by the AI service. The path must be where this clip is
 * stored.
 */
class ReceiveClipResultRequest extends FormRequest
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
        /** @var Clip $clip */
        $clip = $this->route('clip');

        return [
            'error' => ['nullable', 'string', 'max:1000'],
            'path' => ['required_without:error', 'string', Rule::in([$clip->storagePath()])],
            'file_size' => ['required_with:path', 'integer', 'min:1'],
            'md5' => ['required_with:path', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'sha256' => ['required_with:path', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'probe' => ['nullable', 'array'],
        ];
    }
}
