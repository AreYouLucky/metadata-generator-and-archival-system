<?php

namespace App\Http\Requests\All;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Clear the signed-in person's own "Ask me" conversation (nobody else's).
 */
class ClearChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
