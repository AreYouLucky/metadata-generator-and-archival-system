<?php

namespace App\Events;

use App\Enums\PingSource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 0 round-trip check. Broadcast through the queue (Horizon) and Reverb to
 * admins watching the System status page, so one event proves the queue,
 * WebSocket server and browser client all work.
 */
class SystemPingReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $pingId,
        public PingSource $source,
        public string $receivedAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.system')];
    }

    public function broadcastAs(): string
    {
        return 'system.ping';
    }

    /**
     * @return array{ping_id: string, source: string, received_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'ping_id' => $this->pingId,
            'source' => $this->source->value,
            'received_at' => $this->receivedAt,
        ];
    }
}
