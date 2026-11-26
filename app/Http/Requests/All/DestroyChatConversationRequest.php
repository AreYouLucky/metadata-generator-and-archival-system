<?php

namespace App\Http\Requests\All;

use App\Models\ChatConversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Delete one of the signed-in person's own "Ask me" conversations.
 */
class DestroyChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof ChatConversation && $this->user()?->can('delete', $conversation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
