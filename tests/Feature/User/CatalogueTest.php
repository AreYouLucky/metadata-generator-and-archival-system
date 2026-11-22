<?php

namespace Tests\Feature\User;

use App\Enums\AccessAction;
use App\Enums\AccessLevel;
use App\Enums\FileRole;
use App\Enums\MetadataSource;
use App\Enums\PermissionStatus;
use App\Enums\ProcessingStatus;
use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use App\Enums\SubjectType;
use App\Models\Genre;
use App\Models\Project;
use App\Models\ProjectSeries;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordFrame;
use App\Models\RecordRights;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use App\Services\Catalogue\Catalogue;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PROJECT_SPEC §13, Phase 4 "done when": submit → changes requested →
     * resubmit → approve → public under its project, series and date, plays
     * from the proxy, no internal fields → reopen → gone again.
     */
    public function test_a_record_goes_through_review_onto_the_public_site_and_off_again(): void
    {
        Storage::fake('media');
        $admin = User::factory()->admin()->create();
        $series = ProjectSeries::factory()->create(['title' => 'Day 1']);
        $record = Record::factory()->create([
            'title' => 'Opening keynote',
            'project_id' => $series->project_id,
            'project_series_id' => $series->id,
            'date_recorded' => '2026-07-10',
            'processing_status' => ProcessingStatus::Ready,
            'accession_number' => 'ACC-2026-00001',
        ]);
        RecordRights::factory()->for($record)->publicAccess()->create(['rights_statement' => 'Internal rights note']);
        $proxy = $this->proxyFor($record);
        $encoder = $record->creator;

        $this->actingAs($encoder)->postJson(route('records.submit', $record))->assertOk();
        $this->actingAs($admin)
            ->postJson(route('admin.approvals.store', $record), ['action' => 'request_changes', 'notes' => 'Fix the title.'])
            ->assertOk();
        $this->actingAs($encoder)->patchJson(route('records.update', $record), ['title' => 'Opening keynote, Day 1'])->assertOk();
        $this->actingAs($encoder)->postJson(route('records.submit', $record))->assertOk();
        $this->actingAs($admin)->postJson(route('admin.approvals.store', $record), ['action' => 'approve'])->assertOk();

        auth()->logout();

        $this->get(route('catalogue.browse', ['project' => $series->project_id, 'series' => $series->id, 'year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('user/browse-page/browse')
                ->has('records.data', 1)
                ->where('records.data.0.title', 'Opening keynote, Day 1')
                ->where('records.data.0.series', 'Day 1')
                ->where('records.data.0.date', '2026-07-10'));

        $watch = $this->get(route('catalogue.watch', $record))->assertOk();
        $watch->assertInertia(fn (Assert $page) => $page
            ->component('user/watch-page/watch')
            ->where('record.title', 'Opening keynote, Day 1')
            ->where('record.series.title', 'Day 1')
            ->missing('record.accession_number')
            ->missing('record.rights_statement')
            ->missing('record.classification'));
        $this->assertStringNotContainsString('ACC-2026-00001', $watch->getContent());
        $this->assertStringNotContainsString('Internal rights note', $watch->getContent());

        $stream = $this->get(route('catalogue.media', $record))->assertOk();
        $this->assertSame(Storage::disk('media')->path($proxy->storage_location), $stream->baseResponse->getFile()->getPathname());

        $this->actingAs($admin)->postJson(route('admin.records.reopen', $record))->assertOk();
        auth()->logout();

        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
        $this->get(route('catalogue.watch', $record))->assertNotFound();
        $this->get(route('catalogue.media', $record))->assertNotFound();
    }

    /**
     * @param  Closure(Record): void  $spoil
     */
    #[DataProvider('blockers')]
    public function test_a_record_that_may_not_be_distributed_stays_hidden(Closure $spoil): void
    {
        $record = $this->publicRecord();
        $spoil($record);

        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
        $this->get(route('catalogue.watch', $record->id))->assertNotFound();
        $this->get(route('catalogue.captions', [$record->id, 'en']))->assertNotFound();
        $this->assertSame(0, app(Catalogue::class)->records()->count('records.id'));
    }

    /**
     * @return array<string, array{Closure(Record): void}>
     */
    public static function blockers(): array
    {
        return [
            'not approved' => [fn (Record $record) => $record->forceFill(['review_status' => ReviewStatus::Submitted])->save()],
            'not published' => [fn (Record $record) => $record->forceFill(['published_at' => null])->save()],
            'deleted' => [fn (Record $record) => $record->delete()],
            'classified' => [fn (Record $record) => $record->forceFill(['security_classification' => SecurityClassification::Confidential])->save()],
            'internal access' => [fn (Record $record) => $record->rights()->update(['access_level' => AccessLevel::Internal])],
            'permission pending' => [fn (Record $record) => $record->rights()->update(['permission_status' => PermissionStatus::Pending])],
            'embargo in force' => [fn (Record $record) => $record->rights()->update(['embargo_until' => now()->addDay()])],
        ];
    }

    public function test_an_approved_record_whose_rights_were_never_filled_in_is_public(): void
    {
        $record = Record::factory()->approved()->create();

        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.id', $record->id));
        $this->get(route('catalogue.watch', $record))->assertOk();
    }

    public function test_a_view_is_written_to_the_access_log(): void
    {
        $record = $this->publicRecord();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->get(route('catalogue.watch', $record))
            ->assertOk();

        $this->assertDatabaseHas('access_logs', [
            'record_id' => $record->id,
            'user_id' => $viewer->id,
            'action' => AccessAction::View->value,
            'ip_address' => '203.0.113.7',
        ]);
    }

    public function test_only_confirmed_subjects_and_keywords_are_shown_and_searched(): void
    {
        $record = $this->publicRecord();
        $record->subjects()->create(['subject' => 'Climate', 'type' => SubjectType::Topic, 'source' => MetadataSource::Manual, 'is_confirmed' => true]);
        $record->subjects()->create(['subject' => 'Unchecked guess', 'type' => SubjectType::Person, 'source' => MetadataSource::Ai, 'is_confirmed' => false]);
        $record->keywords()->create(['keyword' => 'drafty', 'source' => MetadataSource::Ai, 'is_confirmed' => false]);

        $this->get(route('catalogue.watch', $record))->assertInertia(fn (Assert $page) => $page
            ->has('record.subjects', 1)
            ->where('record.subjects.0.subject', 'Climate')
            ->has('record.keywords', 0));

        $this->get(route('catalogue.browse', ['q' => 'climate']))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->get(route('catalogue.browse', ['q' => 'drafty']))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
    }

    public function test_browse_filters_by_genre_and_date_and_sorts_by_title(): void
    {
        $genre = Genre::factory()->create(['slug' => 'documentary']);
        $this->publicRecord(['title' => 'Beta', 'genre_id' => $genre->id, 'date_recorded' => '2025-03-02']);
        $this->publicRecord(['title' => 'Alpha', 'genre_id' => $genre->id, 'date_recorded' => '2024-01-15']);
        $this->publicRecord(['title' => 'Gamma', 'date_recorded' => '2025-03-20']);

        $this->get(route('catalogue.browse', ['genre' => 'documentary', 'sort' => 'title']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('records.data.0.title', 'Alpha')
                ->where('records.data.1.title', 'Beta'));

        $this->get(route('catalogue.browse', ['year' => 2025, 'month' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('records.data.0.title', 'Gamma')
                ->where('facets.years.0', ['year' => 2025, 'count' => 2])
                ->where('months', [['month' => 3, 'count' => 2]]));
    }

    public function test_an_unknown_sort_is_refused(): void
    {
        $this->get(route('catalogue.browse', ['sort' => 'id; drop table records']))
            ->assertSessionHasErrors('sort');
    }

    public function test_the_catalogue_date_falls_back_to_the_project_date(): void
    {
        $project = Project::factory()->create(['date' => '2023-05-01']);
        $this->publicRecord(['date_recorded' => null, 'project_id' => $project->id]);

        $this->get(route('catalogue.browse', ['year' => 2023]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.date', '2023-05-01'));
    }

    public function test_a_project_without_public_records_is_not_found(): void
    {
        $hidden = Project::factory()->create();
        Record::factory()->create(['project_id' => $hidden->id]);
        $shown = Project::factory()->create();
        $this->publicRecord(['project_id' => $shown->id]);

        $this->get(route('catalogue.projects.show', $hidden))->assertNotFound();
        $this->get(route('catalogue.projects.thumbnail', $hidden))->assertNotFound();
        $this->get(route('catalogue.projects.show', $shown))
            ->assertInertia(fn (Assert $page) => $page
                ->component('user/project-page/project')
                ->has('records.data', 1));
    }

    public function test_captions_are_served_for_a_public_record(): void
    {
        $record = $this->publicRecord();
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'en']);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['start' => 0, 'end' => 2, 'text' => 'Good morning.']);

        $this->get(route('catalogue.captions', [$record, 'en']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/vtt; charset=utf-8')
            ->assertSee('Good morning.');
        $this->get(route('catalogue.captions', [$record, 'fr']))->assertSessionHasErrors('language');
    }

    public function test_an_admin_edit_to_a_public_record_refreshes_the_cached_catalogue(): void
    {
        $record = $this->publicRecord(['title' => 'Old title']);
        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page->where('records.data.0.title', 'Old title'));

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('records.update', $record), ['title' => 'New title'])
            ->assertOk();
        auth()->logout();

        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page->where('records.data.0.title', 'New title'));
    }

    public function test_a_public_records_thumbnail_is_shown_on_its_card_and_served(): void
    {
        Storage::fake('media');
        $record = $this->publicRecord();
        $record->thumbnail_path = "records/{$record->id}/thumbnail/abc.jpg";
        $record->save();
        Storage::disk('media')->put($record->thumbnail_path, 'image');

        $this->get(route('catalogue.browse'))->assertInertia(fn (Assert $page) => $page
            ->where('records.data.0.thumbnail', Catalogue::thumbnailUrl($record)));
        $this->assertStringStartsWith(route('catalogue.thumbnail', $record), (string) Catalogue::thumbnailUrl($record));
        $this->get(route('catalogue.thumbnail', $record))->assertOk();
    }

    public function test_the_thumbnail_of_a_record_that_may_not_be_distributed_is_not_found(): void
    {
        Storage::fake('media');
        $record = Record::factory()->create();
        $record->thumbnail_path = "records/{$record->id}/thumbnail/abc.jpg";
        $record->save();
        Storage::disk('media')->put($record->thumbnail_path, 'image');

        $this->get(route('catalogue.thumbnail', $record))->assertNotFound();
    }

    public function test_the_watch_page_lists_the_key_frames_without_face_counts_and_serves_their_images(): void
    {
        Storage::fake('media');
        $record = $this->publicRecord();
        $frame = RecordFrame::factory()->for($record)->create([
            'time_sec' => 8,
            'description' => 'A title card.',
            'ocr_text' => 'Regional S&T Fair',
            'objects' => [['label' => 'person', 'confidence' => 0.9]],
            'face_count' => 2,
        ]);
        Storage::disk('media')->put($frame->path, 'frame image');

        $this->get(route('catalogue.watch', $record))->assertInertia(fn (Assert $page) => $page
            ->where('frames.0.id', $frame->id)
            ->where('frames.0.description', 'A title card.')
            ->where('frames.0.ocr_text', 'Regional S&T Fair')
            ->where('frames.0.objects', ['person'])
            ->where('frames.0.url', route('catalogue.frames.image', [$record, $frame]))
            ->missing('frames.0.face_count'));
        $this->get(route('catalogue.frames.image', [$record, $frame]))->assertOk();
    }

    public function test_a_frame_of_a_record_that_may_not_be_distributed_is_not_found(): void
    {
        Storage::fake('media');
        $draft = Record::factory()->create();
        $frame = RecordFrame::factory()->for($draft)->create();
        Storage::disk('media')->put($frame->path, 'frame image');
        $public = $this->publicRecord();

        $this->get(route('catalogue.frames.image', [$draft, $frame]))->assertNotFound();
        // Nor through another, public record.
        $this->get(route('catalogue.frames.image', [$public, $frame]))->assertNotFound();
    }

    public function test_the_home_page_lists_the_latest_records(): void
    {
        $this->publicRecord(['title' => 'Published']);
        Record::factory()->create(['title' => 'Draft']);

        $this->get(route('catalogue.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('user/home-page/home')
                ->where('total', 1)
                ->has('latest', 1)
                ->where('latest.0.title', 'Published'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicRecord(array $attributes = []): Record
    {
        $record = Record::factory()->approved()->create($attributes);
        RecordRights::factory()->for($record)->publicAccess()->create();

        return $record;
    }

    /**
     * A ProRes master browsers can't play, and its H.264 proxy.
     */
    private function proxyFor(Record $record): RecordFile
    {
        $master = RecordFile::factory()->for($record)->create(['mime_type' => 'video/quicktime']);
        $master->technicalMetadata()->create(['video_codec' => 'prores', 'audio_codec' => 'pcm_s16le']);
        Storage::disk('media')->put($master->storage_location, 'master');

        $proxy = RecordFile::factory()->for($record)->create(['role' => FileRole::Proxy, 'mime_type' => 'video/mp4']);
        $proxy->technicalMetadata()->create(['video_codec' => 'h264', 'audio_codec' => 'aac']);
        Storage::disk('media')->put($proxy->storage_location, 'proxy');

        return $proxy;
    }
}
