<?php

namespace App\Http\Requests\Encoder;

use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Models\Record;

/**
 * Same choices as a regeneration (preset, instructions): the upload's own
 * choice isn't stored, so the encoder picks again when retrying. Refused
 * once today's processing minutes are used up.
 */
class RetryProcessingRequest extends GenerateMetadataRequest
{
    use ChecksDailyProcessingLimit;

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('retry', $record) ?? false);
    }
}
