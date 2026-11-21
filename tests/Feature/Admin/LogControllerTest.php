<?php

namespace Tests\Feature\Admin;

use App\Enums\AccessAction;
use App\Enums\AuditAction;
use App\Models\AccessLog;
use App\Models\Record;
use App\Models\User;
use App\Models\VideoAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_reads_the_audit_log_newest_first_and_filters_by_action(): void
    {
        $encoder = User::factory()->encoder()->create(['name' => 'Ana']);
        $record = Record::factory()->create(['title' => 'Opening ceremony']);
        VideoAuditLog::factory()->create(['record_id' => $record->id, 'action' => AuditAction::Uploaded, 'performed_by' => $encoder->id, 'performed_at' => now()->subHour()]);
        VideoAuditLog::factory()->create(['record_id' => $record->id, 'action' => AuditAction::Submitted, 'performed_by' => $encoder->id, 'performed_at' => now()]);
        $record->delete();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/logs-page/logs')
                ->has('logs.data', 2)
                ->where('logs.data.0.action', 'submitted')
                ->where('logs.data.0.user', 'Ana')
                ->where('logs.data.0.record.title', 'Opening ceremony')
                ->where('logs.data.0.record.deleted', true));

        $this->get(route('admin.logs.index', ['action' => 'uploaded']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'uploaded'));
    }

    public function test_the_access_log_shows_ip_addresses_and_rejects_audit_only_actions(): void
    {
        $record = Record::factory()->create();
        AccessLog::create(['record_id' => $record->id, 'action' => AccessAction::Download, 'ip_address' => '203.0.113.7', 'accessed_at' => now()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.logs.index', ['type' => 'access']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'access')
                ->where('logs.data.0.action', 'download')
                ->where('logs.data.0.details', '203.0.113.7'));

        $this->get(route('admin.logs.index', ['type' => 'access', 'action' => 'uploaded']))
            ->assertSessionHasErrors('action');
    }

    public function test_encoders_cannot_read_the_logs(): void
    {
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('admin.logs.index'))
            ->assertForbidden();
    }
}
