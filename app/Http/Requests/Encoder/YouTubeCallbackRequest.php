<?php

namespace App\Http\Requests\Encoder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Google's OAuth redirect: `code` and `state` on success, `error` (e.g.
 * access_denied) when the user said no. The controller compares the state
 * with the one it kept in the session.
 */
class YouTubeCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'max:100'],
            'code' => ['required_without:error', 'nullable', 'string', 'max:2048'],
            'error' => ['nullable', 'string', 'max:200'],
            'scope' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
