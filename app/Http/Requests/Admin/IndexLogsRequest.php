<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccessAction;
use App\Enums\AuditAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin Logs page: the archive audit log or the access log, optionally
 * filtered by action (each log has its own list of actions).
 */
class IndexLogsRequest extends FormRequest
{
    public const string AUDIT = 'audit';

    public const string ACCESS = 'access';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actions = $this->input('type') === self::ACCESS ? AccessAction::class : AuditAction::class;

        return [
            'type' => ['nullable', Rule::in([self::AUDIT, self::ACCESS])],
            'action' => ['nullable', Rule::enum($actions)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
