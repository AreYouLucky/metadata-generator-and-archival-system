<?php

namespace App\Http\Requests\Encoder;

use App\Enums\ApiTokenAbility;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new public-API token: a name, what it may do, and when it expires
 * (30, 90 or 365 days, or never).
 */
class StoreApiTokenRequest extends FormRequest
{
    public const int MAX_TOKENS = 20;

    public const array EXPIRY_DAYS = [30, 90, 365];

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
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'distinct', Rule::enum(ApiTokenAbility::class)],
            'expires_in_days' => ['nullable', 'integer', Rule::in(self::EXPIRY_DAYS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'abilities.required' => 'Choose at least one thing the token may do.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user instanceof User && $user->tokens()->count() >= self::MAX_TOKENS) {
                    $validator->errors()->add('name', 'You have '.self::MAX_TOKENS.' tokens, the most allowed. Revoke one first.');
                }
            },
        ];
    }
}
