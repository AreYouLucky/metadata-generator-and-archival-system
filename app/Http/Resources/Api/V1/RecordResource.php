<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A record in the public API's list: identity and where it stands. Only
 * these fields leave the server; the full catalogue record is the export.
 *
 * @mixin Record
 */
class RecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'accession_number' => $this->accession_number,
            'processing_status' => $this->processing_status->value,
            'review_status' => $this->review_status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'links' => [
                'self' => route('api.v1.records.show', $this->resource),
            ],
        ];
    }
}
