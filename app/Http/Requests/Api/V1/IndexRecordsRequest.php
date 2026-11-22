<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApiTokenAbility;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/records?processing_status=&review_status=&updated_since=&per_page=&page=
 * The token needs `records:read`; the list holds what its owner may open.
 */
class IndexRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tokenCan(ApiTokenAbility::ReadRecords->value)
            && $user->can('viewAny', Record::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'processing_status' => ['nullable', Rule::enum(ProcessingStatus::class)],
            'review_status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('archive.api.max_per_page')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
