<?php

namespace App\Events;

use App\Models\Batch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A batch's counts, status or ZIP changed. Broadcast on `batches.{id}`, where
 * the batch page also hears each record's stage progress (RecordProgressUpdated).
 */
class BatchProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Batch $batch) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("batches.{$this->batch->id}")];
    }

    public function broadcastAs(): string
    {
        return 'batch.progress';
    }

    /**
     * @return array{status: string, total: int, completed: int, failed: int, zip_status: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->batch->status->value,
            'total' => $this->batch->total,
            'completed' => $this->batch->completed,
            'failed' => $this->batch->failed,
            'zip_status' => $this->batch->zip_status?->value,
        ];
    }
}
