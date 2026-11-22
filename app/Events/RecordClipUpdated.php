<?php

namespace App\Events;

use App\Models\Clip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A clip started, finished or failed. The Clips panel reloads its list.
 */
class RecordClipUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Clip $clip) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("records.{$this->clip->record_id}")];
    }

    public function broadcastAs(): string
    {
        return 'record.clip-updated';
    }

    /**
     * @return array{clip_id: int, status: string, title: string, error: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'clip_id' => $this->clip->id,
            'status' => $this->clip->status->value,
            'title' => $this->clip->label(),
            'error' => $this->clip->error,
        ];
    }
}
