<?php

namespace App\Notifications;

use App\Models\Record;
use App\Models\RecordFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A fixity check found a file that changed or disappeared. Sent to the
 * record's owner and to every admin; it names the record, never its content.
 */
class FixityMismatch extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Record $record,
        public RecordFile $file,
        public bool $missing,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->missing ? 'is missing from storage' : 'no longer matches the checksums taken at ingest';

        return (new MailMessage)
            ->error()
            ->subject("Fixity check failed: {$this->record->accession_number}")
            ->line("The {$this->file->role->value} file of \"{$this->record->title}\" ({$this->record->accession_number}) {$what}.")
            ->line('Restore it from a backup or the source carrier, then run "Check now" in the Provenance tab.')
            ->action('Open the record', route('records.show', $this->record));
    }
}
