<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\ChecksumStatus;
use App\Enums\PipelineStage;
use App\Enums\Preset;
use App\Enums\ProcessingStatus;
use App\Enums\StageStatus;
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
        });

        $this->complete($record, PipelineStage::Checksum);
    }

    /**
     * @param  array<string, mixed>  $result  validated probe summary + raw_probe
     */
    public function storeProbe(Record $record, RecordFile $file, array $result): void
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

        $this->complete($record, PipelineStage::Probe);
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

            // A re-run replaces the original transcript (translations are separate rows).
            $record->transcripts()->where('is_original', true)->delete();

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

            $metadata = $record->metadata()->create([
                'preset' => Preset::from($result['preset']),
                'custom_prompt' => $result['custom_prompt'] ?? null,
                'model' => $result['model'],
                'payload' => [...$result['metadata'], 'selectedTitle' => $titles[0] ?? null],
                'is_active' => true,
            ]);

            $this->recordUsage($record, $result['usage'] ?? []);

            return $metadata;
        });

        $this->complete($record, PipelineStage::GenerateMetadata);

        return $metadata;
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
