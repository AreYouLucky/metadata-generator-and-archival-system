<?php

namespace App\Http\Requests\Encoder;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Revoke one of your own API tokens. A token id that isn't yours is
 * refused, never looked up for anyone else.
 */
class DestroyApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $token = $this->route('token');

        return $user instanceof User && is_numeric($token) && $user->tokens()->whereKey((int) $token)->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
