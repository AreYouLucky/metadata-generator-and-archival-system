<?php

namespace App\Events;

use App\Http\Controllers\Encoder\TranslationController;
use App\Models\Record;
use App\Models\RecordTranscript;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A translation was queued, moved forward, finished or failed. The workspace
 * updates its Translations tab and caption languages from it.
 */
class RecordTranslationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public Record $record,
        public RecordTranscript $translation,
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
        return 'record.translation-updated';
    }

    /**
     * @return array{translation: array{id: int, language: string, status: string, progress: int, error: string|null, line_count: int, edited_count: int}}
     */
    public function broadcastWith(): array
    {
        return ['translation' => TranslationController::present($this->translation)];
    }
}
