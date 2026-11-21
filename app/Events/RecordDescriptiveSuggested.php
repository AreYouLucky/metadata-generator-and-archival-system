<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Fill with AI" finished: catalogue field suggestions (not saved; the
 * encoder reviews them in the form), or why it failed.
 */
class RecordDescriptiveSuggested implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public Record $record,
        public array $fields,
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
        return 'record.descriptive-suggested';
    }

    /**
     * @return array{fields: array<string, mixed>, error: string|null}
     */
    public function broadcastWith(): array
    {
        return ['fields' => $this->fields, 'error' => $this->error];
    }
}
