<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\MetadataSource;
use App\Enums\SecurityClassification;
use App\Enums\SubjectType;
use App\Models\Record;
use App\Models\RecordRights;
use App\Models\User;
use App\Models\Work;
use App\Services\Records\AccessionNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Archive panel's endpoints (PROJECT_SPEC §11): catalogue fields,
 * subjects and keywords, rights, provenance, attachments and works.
 */
class ArchiveCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_fields_are_saved_and_the_change_is_logged(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->patchJson(route('records.update', $record), [
                'alternative_title' => 'Opening day',
                'date_recorded' => '2026-07-10',
                'date_recorded_precision' => 'day',
                'security_classification' => 'restricted',
                'retention_period' => 'Permanent',
            ])
            ->assertOk()
            ->assertJsonPath('archive.archival.security_classification', 'restricted');

        $record->refresh();
        $this->assertSame(['Opening day', SecurityClassification::Restricted], [$record->alternative_title, $record->security_classification]);
        $this->assertDatabaseHas('video_audit_logs', [
            'record_id' => $record->id,
            'action' => AuditAction::Updated->value,
            'remarks' => 'Changed: alternative_title, date_recorded, date_recorded_precision, security_classification, retention_period',
        ]);
    }

    public function test_invalid_catalogue_values_are_refused_with_readable_messages(): void
    {
        $record = Record::factory()->create();
        Record::factory()->create(['accession_number' => 'ACC-2026-00009']);

        $this->actingAs($record->creator)
            ->patchJson(route('records.update', $record), [
                'accession_number' => 'ACC-2026-00009',
                'latitude' => 10.3,
                'date_recorded' => '2026-07-10',
                'security_classification' => 'cosmic',
            ])
            ->assertJsonValidationErrors([
                'accession_number' => 'Another record already has that accession number.',
                'longitude',
                'date_recorded_precision' => 'Say how exact the recording date is.',
                'security_classification',
            ]);
    }

    public function test_another_encoder_cannot_change_the_record(): void
    {
        $record = Record::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->patchJson(route('records.update', $record), ['title' => 'Mine now'])
            ->assertForbidden();
    }

    public function test_accession_numbers_follow_the_format_and_skip_numbers_taken_by_hand(): void
    {
        config(['archive.accession_number_format' => 'ACC-{year}-{seq:5}']);
        $this->travelTo('2026-11-22');
        Record::factory()->create(['accession_number' => 'ACC-2026-00002']);
        $numbers = app(AccessionNumbers::class);

        $this->assertSame(['ACC-2026-00001', 'ACC-2026-00003'], [$numbers->next(), $numbers->next()]);
    }

    public function test_a_subject_added_by_hand_is_confirmed_and_ai_suggestions_wait(): void
    {
        $record = Record::factory()->create();
        $suggestion = $record->subjects()->create(['subject' => 'DOST', 'type' => SubjectType::Organisation, 'source' => MetadataSource::Ai, 'is_confirmed' => false]);

        $this->actingAs($record->creator)
            ->postJson(route('records.subjects.store', $record), ['subject' => 'Jane Doe', 'type' => 'person'])
            ->assertCreated()
            ->assertJsonPath('subject.is_confirmed', true)
            ->assertJsonPath('subject.source', 'manual');
        $this->actingAs($record->creator)
            ->patchJson(route('records.subjects.update', [$record, $suggestion]), ['is_confirmed' => true])
            ->assertOk();

        $this->assertTrue($suggestion->refresh()->is_confirmed);
        $this->assertSame(MetadataSource::Ai, $suggestion->source);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'remarks' => 'Subject confirmed: DOST']);
    }

    public function test_rejecting_a_keyword_deletes_it_and_another_records_keyword_is_out_of_reach(): void
    {
        $record = Record::factory()->create();
        $keyword = $record->keywords()->create(['keyword' => 'innovation', 'source' => MetadataSource::Ai, 'is_confirmed' => false]);
        $foreign = Record::factory()->create()->keywords()->create(['keyword' => 'other', 'source' => MetadataSource::Manual, 'is_confirmed' => true]);

        $this->actingAs($record->creator)->deleteJson(route('records.keywords.destroy', [$record, $keyword]))->assertOk();
        $this->actingAs($record->creator)->deleteJson(route('records.keywords.destroy', [$record, $foreign]))->assertNotFound();

        $this->assertModelMissing($keyword);
        $this->assertModelExists($foreign);
    }

    public function test_rights_are_saved_and_territories_must_be_country_codes(): void
    {
        $record = Record::factory()->create();
        $rights = [
            'copyright_holder' => 'DOST',
            'copyright_year' => 2026,
            'rights_statement' => null,
            'license' => null,
            'license_url' => null,
            'access_level' => 'public',
            'usage_restrictions' => 'Subject to agency approval',
            'territories' => ['PH'],
            'embargo_until' => '2027-01-31',
            'permission_status' => 'approved',
            'release_consent' => 'available',
        ];

        $this->actingAs($record->creator)->putJson(route('records.rights.update', $record), [...$rights, 'territories' => ['Philippines']])
            ->assertJsonValidationErrors(['territories.0' => 'Use two-letter country codes, e.g. PH.']);
        $this->actingAs($record->creator)->putJson(route('records.rights.update', $record), $rights)
            ->assertOk()
            ->assertJsonPath('archive.embargoed', true);

        $this->assertSame(['PH'], $record->rights()->sole()->territories);
    }

    public function test_provenance_is_saved(): void
    {
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->putJson(route('records.provenance.update', $record), ['source_carrier' => 'camera_card', 'carrier_label' => 'Card A', 'transfer_date' => '2026-07-11'])
            ->assertOk()
            ->assertJsonPath('archive.provenance.carrier_label', 'Card A');
    }

    public function test_a_release_form_is_stored_privately_and_downloads_only_for_the_owner(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();

        $response = $this->actingAs($record->creator)
            ->postJson(route('records.attachments.store', $record), [
                'file' => UploadedFile::fake()->create('consent.pdf', 120, 'application/pdf'),
                'kind' => 'release_form',
            ])
            ->assertCreated();

        $attachment = $record->attachments()->sole();
        $this->assertStringStartsWith("records/{$record->id}/attachments/", $attachment->path);
        $this->assertStringEndsWith('.pdf', $attachment->path);
        $this->assertSame(hash('sha256', Storage::disk('media')->get($attachment->path) ?? ''), $response->json('attachment.sha256_checksum'));

        $this->actingAs($record->creator)->get(route('records.attachments.show', [$record, $attachment]))->assertOk()->assertDownload('consent.pdf');
        $this->actingAs(User::factory()->encoder()->create())->get(route('records.attachments.show', [$record, $attachment]))->assertForbidden();
    }

    public function test_attachments_that_could_run_in_a_browser_are_refused(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();

        $this->actingAs($record->creator)
            ->postJson(route('records.attachments.store', $record), [
                'file' => UploadedFile::fake()->create('form.html', 2, 'text/html'),
                'kind' => 'document',
            ])
            ->assertJsonValidationErrors(['file']);
    }

    public function test_a_record_joins_its_owners_work_but_not_someone_elses(): void
    {
        $record = Record::factory()->create();
        $mine = Work::factory()->for($record->creator, 'creator')->create();
        $theirs = Work::factory()->create();

        $this->actingAs($record->creator)
            ->patchJson(route('records.update', $record), ['work_id' => $mine->id, 'part_number' => 2, 'part_label' => 'Reel 2'])
            ->assertOk()
            ->assertJsonPath('archive.work.parts.0.part_label', 'Reel 2');
        $this->actingAs($record->creator)
            ->patchJson(route('records.update', $record), ['work_id' => $theirs->id])
            ->assertJsonValidationErrors(['work_id' => 'Choose one of your works.']);
        $this->actingAs($record->creator)->getJson(route('works.show', $theirs))->assertForbidden();
    }

    public function test_the_workspace_has_the_archive_and_its_options(): void
    {
        $record = Record::factory()->create(['accession_number' => 'ACC-2026-00042']);
        RecordRights::factory()->for($record)->create(['embargo_until' => now()->addMonth()]);

        $this->actingAs($record->creator)
            ->get(route('records.show', $record))
            ->assertInertia(fn (Assert $page) => $page
                ->where('archive.archival.accession_number', 'ACC-2026-00042')
                ->where('archive.embargoed', true)
                ->where('record.embargo_until', now()->addMonth()->toDateString())
                ->has('archiveOptions.classifications', 5));
    }

    public function test_the_record_list_finds_accession_numbers_and_filters_embargoed_and_classified_records(): void
    {
        $user = User::factory()->encoder()->create();
        $embargoed = Record::factory()->for($user, 'creator')->create(['accession_number' => 'ACC-2026-00077']);
        RecordRights::factory()->for($embargoed)->create(['embargo_until' => now()->addWeek()]);
        $classified = Record::factory()->for($user, 'creator')->create(['security_classification' => SecurityClassification::Confidential]);
        Record::factory()->for($user, 'creator')->create();

        $this->actingAs($user)->get(route('records.index', ['q' => '00077']))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $embargoed->id));
        $this->actingAs($user)->get(route('records.index', ['flag' => 'embargoed']))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $embargoed->id));
        $this->actingAs($user)->get(route('records.index', ['flag' => 'classified']))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.classification', 'confidential')->where('records.data.0.id', $classified->id));
    }

    public function test_admins_see_records_due_for_disposition_review(): void
    {
        $due = Record::factory()->create(['disposition_date' => now()->subDay(), 'disposition_action' => 'review']);
        Record::factory()->create(['disposition_date' => now()->addYear(), 'disposition_action' => 'destroy']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dispositions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dispositions-page/dispositions')
                ->has('records.data', 1)
                ->where('records.data.0.id', $due->id));
        $this->actingAs($due->creator)->get(route('admin.dispositions.index'))->assertForbidden();
    }
}
