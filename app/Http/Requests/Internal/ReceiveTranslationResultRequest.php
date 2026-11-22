<?php

namespace App\Http\Requests\Internal;

use App\Enums\UsageKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A finished translation's lines (signed by the AI service). Only ids and text
 * are accepted; timings always come from the original lines.
 */
class ReceiveTranslationResultRequest extends FormRequest
{
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
            'model' => ['required', 'string', 'max:255'],
            'kept' => ['required', 'integer', 'min:0'],
            'lines' => ['present', 'array', 'max:50000'],
            'lines.*.id' => ['required', 'integer', 'min:1'],
            'lines.*.text' => ['required', 'string', 'max:5000'],
            'usage' => ['present', 'array', 'max:2000'],
            'usage.*.kind' => ['required', Rule::in([UsageKind::LlmInputTokens->value, UsageKind::LlmOutputTokens->value])],
            'usage.*.quantity' => ['required', 'numeric', 'min:0'],
            'usage.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return list<array{id: int, text: string}>
     */
    public function lines(): array
    {
        /** @var array<int, array{id: int|string, text: string}> $lines */
        $lines = $this->validated('lines');

        return array_values(array_map(fn (array $line): array => ['id' => (int) $line['id'], 'text' => $line['text']], $lines));
    }

    /**
     * Batches are summed per kind, so a long translation writes two usage rows, not hundreds.
     *
     * @return list<array{kind: string, quantity: float, estimated_cost: float|null}>
     */
    public function usage(): array
    {
        /** @var array<int, array{kind: string, quantity: float|int|string, estimated_cost?: float|int|string|null}> $usage */
        $usage = $this->validated('usage', []);
        $totals = [];

        foreach ($usage as $item) {
            $total = $totals[$item['kind']] ?? ['kind' => $item['kind'], 'quantity' => 0.0, 'estimated_cost' => null];
            $total['quantity'] += (float) $item['quantity'];

            if (isset($item['estimated_cost'])) {
                $total['estimated_cost'] = ($total['estimated_cost'] ?? 0.0) + (float) $item['estimated_cost'];
            }

            $totals[$item['kind']] = $total;
        }

        return array_values($totals);
    }
}
