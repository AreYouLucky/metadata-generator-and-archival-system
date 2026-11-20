<?php

namespace App\Http\Requests\Admin;

use App\Enums\PingTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSystemPingRequest extends FormRequest
{
    /**
     * Access is enforced by the `role:admin` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', Rule::enum(PingTarget::class)],
        ];
    }

    public function target(): PingTarget
    {
        return PingTarget::from($this->validated('target'));
    }
}
