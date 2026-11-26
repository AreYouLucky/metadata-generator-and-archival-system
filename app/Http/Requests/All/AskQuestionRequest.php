<?php

namespace App\Http\Requests\All;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ask "Ask me" a question (any signed-in, active account). `record_id` is the
 * record whose page the person is on, if any: only a hint, checked against
 * what the account may ask about before it is used. `conversation_id`
 * continues one of the person's saved conversations (the controller checks
 * it is theirs); without it a new conversation starts. Costs AI tokens, so it
 * is rate-limited (`throttle:chat`) and one question runs at a time.
 */
class AskQuestionRequest extends FormRequest
{
    public const int MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => trim($this->input('question'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:'.self::MAX_LENGTH],
            'record_id' => ['nullable', 'string', 'ulid'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
