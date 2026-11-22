<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Generate more" finished: the new title options (already saved on the
 * generation), or why it failed. The workspace adds them to the title list.
 */
class RecordTitlesGenerated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<string>  $titles
     */
    public function __construct(
        public Record $record,
        public int $metadataId,
        public array $titles,
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
        return 'record.titles-generated';
    }

    /**
     * @return array{metadata_id: int, titles: list<string>, error: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'metadata_id' => $this->metadataId,
            'titles' => $this->titles,
            'error' => $this->error,
        ];
    }
}
