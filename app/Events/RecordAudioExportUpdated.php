<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An audio export finished or failed. The Export panel reloads its audio
 * section.
 */
class RecordAudioExportUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public Record $record,
        public string $format,
        public string $status,
        public ?string $error = null,
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
        return 'record.audio-export-updated';
    }

    /**
     * @return array{format: string, status: string, error: string|null}
     */
    public function broadcastWith(): array
    {
        return ['format' => $this->format, 'status' => $this->status, 'error' => $this->error];
    }
}
