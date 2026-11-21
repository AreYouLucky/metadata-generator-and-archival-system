<?php

namespace App\Events;

use App\Enums\PipelineStage;
use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A stage finished and its result is stored. The workspace reloads the props
 * that stage changed (transcript, metadata...) with an Inertia partial reload.
 */
class RecordStageCompleted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public Record $record,
        public PipelineStage $stage,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("records.{$this->record->id}")];
    }

    public function broadcastAs(): string
    {
        return 'record.stage-completed';
    }

    /**
     * @return array{stage: string, processing_status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'stage' => $this->stage->value,
            'processing_status' => $this->record->processing_status->value,
        ];
    }
}
