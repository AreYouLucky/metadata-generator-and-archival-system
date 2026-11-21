<?php

namespace App\Http\Requests\Admin;

use App\Enums\DispositionAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDispositionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::enum(DispositionAction::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
