<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something in the record's archival data changed in the background (an
 * added file was checked, a fixity check finished). The workspace reloads its
 * Archive panel.
 */
class RecordArchiveUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public Record $record,
        public string $reason,
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
        return 'record.archive-updated';
    }

    /**
     * @return array{reason: string}
     */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason];
    }
}
