<?php

namespace App\Http\Requests\All;

use App\Models\ChatConversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One of the signed-in person's own "Ask me" conversations, newest page
 * first; `before` pages back to older messages.
 */
class ShowChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof ChatConversation && $this->user()?->can('view', $conversation) === true;
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
