<?php

namespace App\Http\Requests\Encoder;

use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the record history: search, status filters, sort.
 */
class IndexRecordsRequest extends FormRequest
{
    public const array SORTS = ['newest', 'oldest', 'title'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Record::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'processing_status' => ['nullable', Rule::enum(ProcessingStatus::class)],
            'review_status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
