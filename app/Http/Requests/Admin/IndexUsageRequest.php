<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin Usage page and its CSV: a date range (this month by default, at
 * most a year), optionally one encoder.
 */
class IndexUsageRequest extends FormRequest
{
    private const int MAX_DAYS = 366;

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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now()->addDay()->toDateString()],
            'encoder' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to.after_or_equal' => 'The end date must be on or after the start date.'];
    }

    /**
     * The range, clamped to MAX_DAYS so one request can't sum years of rows
     * day by day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $to = $this->validated('to') ? CarbonImmutable::parse((string) $this->validated('to')) : CarbonImmutable::today();
        $from = $this->validated('from') ? CarbonImmutable::parse((string) $this->validated('from')) : $to->startOfMonth();

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }

    public function encoderId(): ?int
    {
        $encoder = $this->validated('encoder');

        return $encoder === null ? null : (int) $encoder;
    }
}
