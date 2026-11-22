<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApiTokenAbility;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/records/{record}: the token needs `records:read` and its owner
 * must be allowed to open the record.
 */
class ShowRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $record = $this->route('record');

        return $user !== null
            && $record instanceof Record
            && $user->tokenCan(ApiTokenAbility::ReadRecords->value)
            && $user->can('view', $record);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
