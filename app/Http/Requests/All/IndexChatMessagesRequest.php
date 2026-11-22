<?php

namespace App\Http\Requests\All;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in person's own "Ask me" conversation, newest page first;
 * `before` pages back to older messages.
 */
class IndexChatMessagesRequest extends FormRequest
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
        return [
            'before' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
