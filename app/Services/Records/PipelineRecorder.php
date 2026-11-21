<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\ChecksumStatus;
use App\Enums\FileRole;
use App\Enums\FixityTrigger;
use App\Enums\MetadataSource;
use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
use App\Enums\SubjectType;
use App\Enums\TaskStatus;
use App\Enums\UsageKind;
use App\Events\RecordProgressUpdated;
use App\Events\RecordStageCompleted;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\RecordMetadata;
use App\Models\RecordStage;
use App\Models\RecordTechnicalMetadata;
use App\Models\RecordTranscript;
use App\Models\UsageRecord;
use App\Models\VideoAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes what the pipeline reports into the database: stage states, each
 * stage's result, usage, audit entries, and the record's overall processing
 * status. Every write is idempotent enough for a retried callback.
 */
class PipelineRecorder
{
    private const int SEGMENT_INSERT_BATCH = 500;

    public function progress(Record $record, PipelineStage $stage, StageStatus $status, int $progress = 0, ?string $message = null): RecordStage
    {
        $row = $this->stage($record, $stage);
        $row->status = $status;
        $row->progress = $status === StageStatus::Running ? max(0, min(99, $progress)) : $row->progress;
        $row->message = $message;

        if ($status === StageStatus::Running && ($row->started_at === null || $row->finished_at !== null)) {
            $row->started_at = now();
            $row->finished_at = null;
        }

        if (in_array($status, [StageStatus::Failed, StageStatus::Skipped], true)) {
            $row->finished_at = now();
        }

        $row->save();
        $this->refreshStatus($record);

        rescue(fn () => RecordProgressUpdated::dispatch($record, $row), report: false);

        return $row;
    }

    /**
     * @param  array{md5: string, sha256: string, file_size: int}  $result
     */
    public function storeChecksum(Record $record, RecordFile $file, array $result): void
    {
        $this->applyChecksums($record, $file, $result);
        $this->complete($record, PipelineStage::Checksum);
    }

    /**
     * Store a file's ingest checksums (master, added file or proxy) and log
     * them. When the encoder supplied expected checksums from a manifest, the
     * comparison is also a fixity check (`triggered_by: ingest`).
     *
     * @param  array{md5: string, sha256: string, file_size: int}  $result
     */
    public function applyChecksums(Record $record, RecordFile $file, array $result): void
    {
        $mismatches = array_filter([
            'MD5' => $file->expected_md5 !== null && $file->expected_md5 !== $result['md5'],
            'SHA-256' => $file->expected_sha256 !== null && $file->expected_sha256 !== $result['sha256'],
        ]);

        DB::transaction(function () use ($record, $file, $result, $mismatches): void {
            $file->md5_checksum = $result['md5'];
            $file->sha256_checksum = $result['sha256'];
            $file->file_size = $result['file_size'];
            $file->checksum_status = $mismatches === [] ? ChecksumStatus::Ok : ChecksumStatus::Mismatch;
            $file->save();

            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => $file->id,
                'action' => $mismatches === [] ? AuditAction::ChecksumVerified : AuditAction::ChecksumFailed,
                'performed_by' => null,
                'performed_at' => now(),
                'remarks' => $mismatches === []
                    ? "Ingest checksums: MD5 {$result['md5']}, SHA-256 {$result['sha256']}."
                    : implode(' and ', array_keys($mismatches)).' did not match the expected checksum supplied at upload.',
            ]);

            foreach (['md5' => $file->expected_md5, 'sha256' => $file->expected_sha256] as $algorithm => $expected) {
                if ($expected !== null) {
                    $file->fixityChecks()->create([
                        'algorithm' => $algorithm,
                        'expected' => $expected,
                        'actual' => $result[$algorithm],
                        'matched' => $expected === $result[$algorithm],
                        'triggered_by' => FixityTrigger::Ingest,
                        'checked_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $result  validated probe summary + raw_probe
     */
    public function storeProbe(Record $record, RecordFile $file, array $result): void
    {
        $this->applyProbe($file, $result);
        $this->complete($record, PipelineStage::Probe);
    }

    /**
     * Store a probe summary as the file's technical metadata.
     *
     * @param  array<string, mixed>  $result  validated probe summary (+ raw_probe)
     */
    public function applyProbe(RecordFile $file, array $result): void
    {
        /** @var array<string, mixed> $raw */
        $raw = $result['raw_probe'] ?? [];
        $raw['container_tags'] = $result['container_tags'] ?? [];

        // Through the relation, so the (unfillable) record_file_id is set by the foreign key.
        $file->technicalMetadata()->updateOrCreate(
            [],
            [
                ...array_intersect_key($result, array_flip((new RecordTechnicalMetadata)->getFillable())),
                'raw_probe' => $raw,
            ],
        );
    }

    /**
     * The access copy for a master browsers can't play: replaces any earlier
     * proxy (the file path is the same, so only the rows change), with its
     * own checksums and technical metadata, logged as `transcoded`.
     *
     * @param  array{path: string, md5: string, sha256: string, file_size: int, probe: array<string, mixed>}  $result
     */
    public function storeProxy(Record $record, array $result): void
    {
        DB::transaction(function () use ($record, $result): void {
            $record->files()->where('role', FileRole::Proxy)->delete();

            $file = new RecordFile([
                'role' => FileRole::Proxy,
                'storage_location' => $result['path'],
                'original_filename' => 'access.mp4',
                'mime_type' => 'video/mp4',
                'file_size' => $result['file_size'],
            ]);
            $file->id = (string) Str::ulid();
            $file->md5_checksum = $result['md5'];
            $file->sha256_checksum = $result['sha256'];
            $file->checksum_status = ChecksumStatus::Ok;
            $record->files()->save($file);

            $this->applyProbe($file, $result['probe']);

            $probe = $result['probe'];
            VideoAuditLog::create([
                'record_id' => $record->id,
                'record_file_id' => $file->id,
                'action' => AuditAction::Transcoded,
                'performed_by' => null,
                'performed_at' => now(),
                'remarks' => sprintf(
                    'Access copy made: %s/%s %s, SHA-256 %s.',
                    $probe['video_codec'] ?? '?',
                    $probe['audio_codec'] ?? 'no audio',
                    $probe['resolution'] ?? '',
                    $result['sha256'],
                ),
            ]);
        });

        $this->complete($record, PipelineStage::Proxy);
    }

    /**
     * @param  array{language?: string|null, language_confidence?: float|null, segments: list<array{start: float, end: float, text: string}>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}  $result
     */
    public function storeTranscript(Record $record, array $result): void
    {
        DB::transaction(function () use ($record, $result): void {
            $language = $result['language'] ?? null;

            $record->language = $language;
            $record->language_confidence = $result['language_confidence'] ?? null;
            $record->save();

            // A re-run replaces the original transcript. Translations are separate rows
            // and stay, except one into the language the original now turns out to be in.
            $record->transcripts()
                ->where(fn ($query) => $query->where('is_original', true)->orWhere('language', $language ?? 'und'))
                ->delete();

            $transcript = new RecordTranscript(['language' => $language ?? 'und', 'is_original' => true]);
            $transcript->status = TaskStatus::Done;
            $record->transcripts()->save($transcript);

            $now = now();
            $rows = [];

            foreach ($result['segments'] as $position => $segment) {
                $rows[] = [
                    'record_transcript_id' => $transcript->id,
                    'position' => $position + 1,
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                    'text' => $segment['text'],
                    'original_text' => $segment['text'],
                    'is_edited' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::SEGMENT_INSERT_BATCH) as $batch) {
                DB::table('record_segments')->insert($batch);
            }

            $this->recordUsage($record, $result['usage'] ?? []);
        });

        $this->complete($record, PipelineStage::Transcribe);
    }

    /**
     * Store a generation as the record's active metadata; earlier ones stay as history.
     *
     * @param  array{preset: string, custom_prompt?: string|null, model: string, metadata: array<string, mixed>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}  $result
     */
    public function storeMetadata(Record $record, array $result): RecordMetadata
    {
        $metadata = DB::transaction(function () use ($record, $result): RecordMetadata {
            $record->metadata()->where('is_active', true)->update(['is_active' => false]);

            /** @var list<string> $titles */
            $titles = $result['metadata']['titles'] ?? [];

            $payload = [...$result['metadata'], 'selectedTitle' => $titles[0] ?? null];

            // A suggested location is kept with the ids of the places that
            // matched, so the Location tab can apply it in one click.
            if (is_array($payload['suggestedLocation'] ?? null)) {
                /** @var array{text: string, region?: string|null, province?: string|null, city?: string|null, barangay?: string|null} $location */
                $location = $payload['suggestedLocation'];
                $payload['suggestedLocation'] = [...$location, ...app(LocationResolver::class)->resolve($location)];
            }

            $metadata = $record->metadata()->create([
                'preset' => Preset::from($result['preset']),
                'custom_prompt' => $result['custom_prompt'] ?? null,
                'model' => $result['model'],
                'payload' => $payload,
                'is_active' => true,
            ]);

            $this->storeSuggestions($record, $payload);

            $this->recordUsage($record, $result['usage'] ?? []);

            return $metadata;
        });

        $this->complete($record, PipelineStage::GenerateMetadata);

        return $metadata;
    }

    /**
     * AI subjects and keywords become unconfirmed archive entries (source
     * `ai`) unless the record already has them; only an encoder confirms them.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeSuggestions(Record $record, array $payload): void
    {
        $subjects = $record->subjects()->pluck('subject')->map(fn (string $subject): string => mb_strtolower($subject))->all();

        foreach (is_array($payload['suggestedSubjects'] ?? null) ? $payload['suggestedSubjects'] : [] as $suggestion) {
            $name = trim((string) ($suggestion['name'] ?? ''));

            if ($name === '' || in_array(mb_strtolower($name), $subjects, true)) {
                continue;
            }

            $record->subjects()->create([
                'subject' => mb_substr($name, 0, 250),
                'type' => SubjectType::tryFrom((string) ($suggestion['type'] ?? '')) ?? SubjectType::Topic,
                'source' => MetadataSource::Ai,
                'is_confirmed' => false,
            ]);
            $subjects[] = mb_strtolower($name);
        }

        $keywords = $record->keywords()->pluck('keyword')->map(fn (string $keyword): string => mb_strtolower($keyword))->all();

        foreach (is_array($payload['keywords'] ?? null) ? $payload['keywords'] : [] as $keyword) {
            $keyword = mb_substr(trim((string) $keyword), 0, 100);

            if ($keyword === '' || in_array(mb_strtolower($keyword), $keywords, true)) {
                continue;
            }

            $record->keywords()->create(['keyword' => $keyword, 'source' => MetadataSource::Ai, 'is_confirmed' => false]);
            $keywords[] = mb_strtolower($keyword);
        }
    }

    public function complete(Record $record, PipelineStage $stage): void
    {
        $row = $this->stage($record, $stage);
        $row->status = StageStatus::Done;
        $row->progress = 100;
        $row->message = null;
        $row->started_at ??= now();
        $row->finished_at = now();
        $row->save();

        $this->refreshStatus($record);

        rescue(fn () => RecordStageCompleted::dispatch($record, $stage), report: false);
        rescue(fn () => RecordProgressUpdated::dispatch($record, $row), report: false);
    }

    /**
     * Put every active stage back to pending before the pipeline runs again.
     * The record returns to `queued`; earlier results are replaced as the
     * stages complete (metadata keeps its history).
     */
    public function resetStages(Record $record): void
    {
        $record->stages()
            ->whereIn('stage', PipelineStage::active())
            ->update([
                'status' => StageStatus::Pending,
                'progress' => 0,
                'message' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);

        $this->refreshStatus($record);
    }

    /**
     * queued -> processing -> ready | failed, derived from the active stages.
     */
    public function refreshStatus(Record $record): void
    {
        $active = array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::active());
        $stages = $record->stages()->whereIn('stage', $active)->get(['stage', 'status', 'message']);

        $terminal = $stages->every(fn (RecordStage $stage): bool => in_array(
            $stage->status,
            [StageStatus::Done, StageStatus::Failed, StageStatus::Skipped],
            true,
        ));
        $failed = $stages->first(fn (RecordStage $stage): bool => $stage->status === StageStatus::Failed);
        $started = $stages->contains(fn (RecordStage $stage): bool => $stage->status !== StageStatus::Pending);

        $record->processing_status = match (true) {
            $terminal && $failed !== null => ProcessingStatus::Failed,
            $terminal => ProcessingStatus::Ready,
            $started => ProcessingStatus::Processing,
            default => ProcessingStatus::Queued,
        };
        $record->processing_error = $failed !== null
            ? "{$failed->stage->label()}: ".($failed->message ?? 'failed')
            : null;
        $record->save();
    }

    /**
     * @param  list<array{kind: string, quantity: float, estimated_cost?: float|null}>  $usage
     */
    public function recordUsage(Record $record, array $usage): void
    {
        foreach ($usage as $item) {
            if ($item['quantity'] <= 0) {
                continue;
            }

            UsageRecord::create([
                'user_id' => $record->created_by,
                'record_id' => $record->id,
                'kind' => UsageKind::from($item['kind']),
                'quantity' => $item['quantity'],
                'estimated_cost' => $item['estimated_cost'] ?? null,
                'occurred_at' => now(),
            ]);
        }
    }

    private function stage(Record $record, PipelineStage $stage): RecordStage
    {
        return $record->stages()->firstOrCreate(['stage' => $stage]);
    }
}
