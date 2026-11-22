<?php

namespace App\Http\Requests\Encoder;

use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Http\Requests\Concerns\HasRecordDetailRules;
use App\Models\Batch;
use App\Rules\PublicMediaUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Several finished uploads and/or web addresses, filed and processed with the
 * same choices; each becomes its own record (PROJECT_SPEC §8, POST /batches).
 */
class StoreBatchRequest extends FormRequest
{
    use ChecksDailyProcessingLimit, HasRecordDetailRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Batch::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1', 'max:'.(int) config('archive.max_batch_size')],
            'items.*' => ['required', 'array:upload_id,url,title'],
            'items.*.upload_id' => ['required_without:items.*.url', 'prohibits:items.*.url', 'nullable', 'uuid', 'distinct'],
            'items.*.url' => ['required_without:items.*.upload_id', 'nullable', 'string', new PublicMediaUrl],
            'items.*.title' => ['nullable', 'string', 'max:250'],
            ...$this->recordDetailRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->recordDetailMessages(),
            'items.required' => 'Add at least one video.',
            'items.max' => 'A batch holds at most :max videos.',
            'items.*.upload_id.required_without' => 'Each item needs a file or a web address.',
            'items.*.url.required_without' => 'Each item needs a file or a web address.',
            'items.*.upload_id.prohibits' => 'An item is a file or a web address, not both.',
        ];
    }

    /**
     * @return list<array{upload_id: string|null, url: string|null, title: string|null}>
     */
    public function items(): array
    {
        /** @var list<array{upload_id?: string|null, url?: string|null, title?: string|null}> $items */
        $items = $this->validated('items');

        return array_map(fn (array $item): array => [
            'upload_id' => $item['upload_id'] ?? null,
            'url' => $item['url'] ?? null,
            'title' => $item['title'] ?? null,
        ], $items);
    }
}
