<?php

namespace App\Events;

use App\Models\YoutubePublication;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A YouTube publication was queued, uploaded further, finished or failed.
 * The Publish panel reloads its list.
 */
class RecordPublicationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public YoutubePublication $publication) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("records.{$this->publication->record_id}")];
    }

    public function broadcastAs(): string
    {
        return 'record.publication-updated';
    }

    /**
     * @return array{publication_id: int, status: string, percent: int, error: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'publication_id' => $this->publication->id,
            'status' => $this->publication->status->value,
            'percent' => $this->publication->uploadPercent(),
            'error' => $this->publication->error,
        ];
    }
}
