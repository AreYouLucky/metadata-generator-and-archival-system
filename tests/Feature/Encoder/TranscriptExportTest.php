<?php

namespace Tests\Feature\Encoder;

use App\Enums\AccessLevel;
use App\Enums\AuditAction;
use App\Enums\ContributorRole;
use App\Enums\Preset;
use App\Enums\TaskStatus;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SRT, VTT, TXT, CSV and Markdown exports, the player's caption track and the
 * Export panel's preview (PROJECT_SPEC §8, Exports).
 */
class TranscriptExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_srt_has_numbered_cues_with_comma_timecodes_and_speaker_prefixes(): void
    {
        $record = $this->recordWithTranscript();

        $response = $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'srt']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-subrip; charset=utf-8')
            ->assertDownload();

        $this->assertSame(
            "1\n00:00:00,000 --> 00:00:04,200\nHost: Welcome to the fair.\n\n"
            ."2\n01:02:03,450 --> 01:02:05,000\nBuhay ang agham.\n",
            $response->getContent(),
        );
    }

    public function test_vtt_escapes_markup_and_uses_voice_spans(): void
    {
        $record = $this->recordWithTranscript(firstLine: 'Use <b>bold</b> & a --> arrow.');

        $content = (string) $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'vtt']))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith("WEBVTT\n\n00:00:00.000 --> 00:00:04.200\n<v Host>", $content);
        $this->assertStringContainsString('Use &lt;b&gt;bold&lt;/b&gt; &amp; a → arrow.', $content);
        $this->assertStringNotContainsString('<b>', $content);
    }

    public function test_txt_adds_timestamps_only_when_asked(): void
    {
        $record = $this->recordWithTranscript();

        $plain = $this->actingAs($record->creator)->get(route('records.export', [$record, 'format' => 'txt']))->getContent();
        $timed = $this->actingAs($record->creator)->get(route('records.export', [$record, 'format' => 'txt', 'timestamps' => 1]))->getContent();

        $this->assertSame("Host: Welcome to the fair.\nBuhay ang agham.\n", $plain);
        $this->assertSame("[0:00] Host: Welcome to the fair.\n[1:02:03] Buhay ang agham.\n", $timed);
    }

    public function test_csv_has_one_row_per_line_and_neutralises_formulas(): void
    {
        $record = $this->recordWithTranscript(firstLine: '=HYPERLINK("http://evil.test")');

        $content = (string) $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'csv']))
            ->assertOk()
            ->getContent();

        $rows = array_map('str_getcsv', explode("\n", trim(substr($content, 3))));
        $this->assertStringStartsWith("\u{FEFF}", $content);
        $this->assertSame(['start', 'end', 'speaker', 'text'], $rows[0]);
        $this->assertSame(['0.000', '4.200', 'Host', '\'=HYPERLINK("http://evil.test")'], $rows[1]);
        $this->assertSame(['3723.450', '3725.000', '', 'Buhay ang agham.'], $rows[2]);
    }

    public function test_a_translation_is_exported_with_its_language_in_the_file_name(): void
    {
        $record = $this->recordWithTranscript();
        $translation = RecordTranscript::factory()->for($record)->translation('fil')->create();
        RecordSegment::factory()->for($translation, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 4.2, 'text' => 'Maligayang pagdating.']);

        $response = $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'srt', 'language' => 'fil']))
            ->assertOk();

        $this->assertStringContainsString('Maligayang pagdating.', (string) $response->getContent());
        $this->assertStringEndsWith('-fil.srt"', (string) $response->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'action' => AuditAction::Exported->value, 'remarks' => 'Format: srt, language: fil']);
    }

    public function test_a_language_the_record_has_no_finished_transcript_in_is_refused(): void
    {
        $record = $this->recordWithTranscript();
        RecordTranscript::factory()->for($record)->translation('ceb')->create(['status' => TaskStatus::Processing]);

        $this->actingAs($record->creator)
            ->getJson(route('records.export', [$record, 'format' => 'srt', 'language' => 'ceb']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    public function test_markdown_has_the_summary_credits_chapters_quotes_rights_and_escaped_text(): void
    {
        $record = $this->recordWithTranscript();
        $record->update(['title' => 'Fair <script>alert(1)</script>', 'description' => null]);
        $record->contributors()->create(['role' => ContributorRole::Producer, 'name' => 'Ana Cruz', 'sort_order' => 1]);
        $record->rights()->create(['copyright_holder' => 'DOST', 'copyright_year' => 2026, 'license' => 'Agency licence', 'access_level' => AccessLevel::Public, 'usage_restrictions' => 'Ask first', 'embargo_until' => '2027-01-31']);
        $record->metadata()->create([
            'preset' => Preset::Youtube,
            'model' => 'gpt-6.2-sol',
            'is_active' => true,
            'payload' => [
                'titles' => ['Opening'],
                'description' => 'The opening ceremony.',
                'summary' => 'Officials open the fair.',
                'tags' => ['science fair'],
                'hashtags' => ['#STFair'],
                'chapters' => [['start' => 0, 'title' => 'Intro'], ['start' => 75, 'title' => 'Speeches']],
                'quotes' => [['start' => 3723.45, 'end' => 3725, 'speaker' => null, 'text' => 'Buhay ang agham.']],
            ],
        ]);

        $markdown = (string) $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'md']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->getContent();

        $this->assertStringStartsWith('# Fair \<script\>alert(1)\</script\>', $markdown);
        $this->assertStringNotContainsString('<script>', $markdown);
        $this->assertStringContainsString("## Description\n\nThe opening ceremony.", $markdown);
        $this->assertStringContainsString('- **Producer:** Ana Cruz', $markdown);
        $this->assertStringContainsString("- `0:00` Intro\n- `1:15` Speeches", $markdown);
        $this->assertStringContainsString('> Buhay ang agham. — `1:02:03`', $markdown);
        $this->assertStringContainsString('- **Copyright:** © 2026 DOST', $markdown);
        $this->assertStringContainsString('- **Embargo until:** 2027-01-31', $markdown);
        $this->assertStringContainsString('**[0:00]** Host: Welcome to the fair.', $markdown);
    }

    public function test_a_preview_is_inline_plain_text_and_not_logged(): void
    {
        $record = $this->recordWithTranscript();

        $response = $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'vtt', 'preview' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->assertNull($response->headers->get('Content-Disposition'));
        $this->assertDatabaseMissing('video_audit_logs', ['record_id' => $record->id, 'action' => AuditAction::Exported->value]);
    }

    public function test_captions_are_served_as_vtt_to_the_owner_only(): void
    {
        $record = $this->recordWithTranscript();

        $this->actingAs($record->creator)
            ->get(route('records.captions', [$record, 'en']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/vtt; charset=utf-8');
        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.captions', [$record, 'en']))
            ->assertForbidden();
        $this->actingAs($record->creator)
            ->getJson(route('records.captions', [$record, 'ja']))
            ->assertUnprocessable();
        $this->assertDatabaseMissing('video_audit_logs', ['record_id' => $record->id, 'action' => AuditAction::Exported->value]);
    }

    private function recordWithTranscript(string $firstLine = 'Welcome to the fair.'): Record
    {
        $record = Record::factory()->create();
        $speaker = RecordSpeaker::factory()->for($record)->create(['label' => 'S1', 'display_name' => 'Host']);
        $transcript = RecordTranscript::factory()->for($record)->create(['language' => 'en']);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 4.2, 'text' => $firstLine, 'record_speaker_id' => $speaker->id]);
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 2, 'start' => 3723.45, 'end' => 3725, 'text' => 'Buhay ang agham.']);

        return $record;
    }
}
