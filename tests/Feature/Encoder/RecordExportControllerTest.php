<?php

namespace Tests\Feature\Encoder;

use App\Enums\AuditAction;
use App\Enums\Preset;
use App\Models\RecordFile;
use App\Models\RecordSegment;
use App\Models\RecordTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_json_export_has_the_files_codecs_checksums_transcript_and_ai_block(): void
    {
        $file = RecordFile::factory()->create([
            'md5_checksum' => str_repeat('a', 32),
            'sha256_checksum' => str_repeat('b', 64),
        ]);
        $record = $file->record;
        $file->technicalMetadata()->create([
            'file_format' => 'QuickTime / MOV',
            'duration' => 312.5,
            'video_codec' => 'prores',
            'audio_codec' => 'pcm_s24le',
            'audio_tracks' => [],
        ]);
        $transcript = RecordTranscript::factory()->for($record)->create();
        RecordSegment::factory()->for($transcript, 'transcript')->create(['position' => 1, 'start' => 0, 'end' => 4.2, 'text' => 'Welcome.']);
        $record->metadata()->create([
            'preset' => Preset::Youtube,
            'model' => 'gpt-6.2-sol',
            'payload' => ['titles' => ['Opening'], 'selectedTitle' => 'Opening', 'chapters' => [['start' => 0, 'title' => 'Intro']]],
            'is_active' => true,
        ]);

        $response = $this->actingAs($record->creator)
            ->get(route('records.export', [$record, 'format' => 'json']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertDownload();

        $json = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('1.0', $json['schemaVersion']);
        $this->assertSame($record->id, $json['record']['id']);
        $this->assertSame('prores', $json['technical']['files'][0]['video']['codec']);
        $this->assertSame('pcm_s24le', $json['technical']['files'][0]['audio']['codec']);
        $this->assertSame(['md5' => str_repeat('a', 32), 'sha256' => str_repeat('b', 64), 'status' => 'ok'], $json['technical']['files'][0]['checksums']);
        $this->assertSame('Welcome.', $json['transcript'][0]['text']);
        $this->assertSame('youtube', $json['ai']['preset']);
        $this->assertSame('Opening', $json['ai']['selectedTitle']);
        $this->assertDatabaseHas('video_audit_logs', ['record_id' => $record->id, 'action' => AuditAction::Exported->value]);
    }

    public function test_another_encoder_cannot_export_the_record(): void
    {
        $file = RecordFile::factory()->create();

        $this->actingAs(User::factory()->encoder()->create())
            ->get(route('records.export', $file->record))
            ->assertForbidden();
    }

    public function test_formats_from_later_phases_are_rejected(): void
    {
        $file = RecordFile::factory()->create();

        $this->actingAs($file->record->creator)
            ->getJson(route('records.export', [$file->record, 'format' => 'srt']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }
}
