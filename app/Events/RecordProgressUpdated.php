<?php

namespace App\Events;

use App\Models\Record;
use App\Models\RecordStage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A stage started, moved, failed or was skipped. Broadcast immediately (not
 * queued) so progress isn't held up behind slow queued jobs; PipelineRecorder
 * dispatches it with rescue() so a Reverb outage never fails a callback.
 */
class RecordProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public Record $record,
        public RecordStage $stage,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("records.{$this->record->id}"),
            // The batch page follows every record of its batch on one channel.
            ...($this->record->batch_id !== null ? [new PrivateChannel("batches.{$this->record->batch_id}")] : []),
        ];
    }

    public function broadcastAs(): string
    {
        return 'record.progress';
    }

    /**
     * @return array{record_id: string, processing_status: string, stage: array{stage: string, status: string, progress: int, message: string|null}}
     */
    public function broadcastWith(): array
    {
        return [
            'record_id' => $this->record->id,
            'processing_status' => $this->record->processing_status->value,
            'stage' => [
                'stage' => $this->stage->stage->value,
                'status' => $this->stage->status->value,
                'progress' => $this->stage->progress,
                'message' => $this->stage->message,
            ],
        ];
    }
}
