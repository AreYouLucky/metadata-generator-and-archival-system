<?php

namespace Tests\Feature\Models;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\VideoAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class VideoAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_entry_throws_and_leaves_the_row_unchanged(): void
    {
        $entry = VideoAuditLog::factory()->create(['action' => AuditAction::Uploaded]);

        try {
            $entry->update(['action' => AuditAction::Approved]);
            $this->fail('Updating an audit log entry should throw.');
        } catch (LogicException) {
            $this->assertSame(AuditAction::Uploaded, $entry->fresh()?->action);
        }
    }

    public function test_deleting_an_entry_throws_and_keeps_the_row(): void
    {
        $entry = VideoAuditLog::factory()->create();

        try {
            $entry->delete();
            $this->fail('Deleting an audit log entry should throw.');
        } catch (LogicException) {
            $this->assertModelExists($entry);
        }
    }

    public function test_entries_survive_when_their_record_is_permanently_deleted(): void
    {
        $record = Record::factory()->create();
        $entry = VideoAuditLog::factory()->create(['record_id' => $record->id]);

        $record->forceDelete();

        $this->assertModelMissing($record);
        $this->assertSame($record->id, $entry->fresh()?->record_id);
    }
}
