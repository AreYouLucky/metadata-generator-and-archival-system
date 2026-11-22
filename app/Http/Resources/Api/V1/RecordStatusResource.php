<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\PipelineStage;
use App\Models\Record;
use App\Models\RecordStage;
use App\Services\Catalogue\Distribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One record's processing status, every pipeline stage, its review status
 * and whether it may be exported through the API (the distribution policy),
 * with the reasons when it may not.
 *
 * @mixin Record
 */
class RecordStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Record $record */
        $record = $this->resource;
        $blockers = app(Distribution::class)->blockers($record);
        $rows = $record->stages()->get(['stage', 'status', 'progress', 'message'])->keyBy(fn (RecordStage $stage): string => $stage->stage->value);

        return [
            ...(new RecordResource($record))->toArray($request),
            'processing_error' => $record->processing_error,
            'stages' => array_values(array_filter(array_map(function (PipelineStage $stage) use ($rows): ?array {
                /** @var RecordStage|null $row */
                $row = $rows->get($stage->value);

                return $row === null ? null : [
                    'stage' => $stage->value,
                    'label' => $stage->label(),
                    'status' => $row->status->value,
                    'progress' => $row->progress,
                    'message' => $row->message,
                ];
            }, PipelineStage::tracked()))),
            'exportable' => $blockers === [],
            'export_blockers' => $blockers,
            'webhook' => $record->webhook_url === null ? null : [
                'url' => $record->webhook_url,
                'sent_at' => $record->webhook_sent_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.records.show', $record),
                'export' => $blockers === [] ? route('api.v1.records.export', $record) : null,
                'workspace' => route('records.show', $record),
            ],
        ];
    }
}
