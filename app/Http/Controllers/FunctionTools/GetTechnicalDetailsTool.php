<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\FixityCheck;
use App\Models\Record;
use App\Models\RecordFile;
use App\Services\Chat\ChatScope;

/**
 * Technical metadata of each stored file (master, mezzanine, access copy):
 * format, codecs, resolution, frame rate, audio, checksum status, and the
 * latest fixity check per file. Storage paths are never included.
 */
class GetTechnicalDetailsTool extends RecordTool
{
    public function name(): string
    {
        return 'get_technical_details';
    }

    public function description(): string
    {
        return 'Technical metadata of the stored files (master, mezzanine, access copy): format, size, duration, '
            .'video codec and profile, resolution, frame rate, scan type, bit depth, colour, audio codec and '
            .'channels, start timecode, checksum status, and the latest fixity check.';
    }

    protected function properties(): array
    {
        return [];
    }

    protected function recordRules(): array
    {
        return [];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        if (! $internal) {
            return ToolResult::unavailable('Technical file details are not public for this record.');
        }

        $files = $record->files()
            // Never the raw probe output (large, and not needed).
            ->with('technicalMetadata:id,record_file_id,file_format,duration,bitrate,start_timecode,video_codec,video_profile,resolution,frame_rate,aspect_ratio,scan_type,bit_depth,chroma_subsampling,color_space,audio_codec,audio_channels,audio_sample_rate')
            ->get(['id', 'record_id', 'role', 'original_filename', 'mime_type', 'file_size', 'md5_checksum', 'sha256_checksum', 'checksum_status']);

        if ($files->isEmpty()) {
            return ToolResult::unavailable('No files are stored for this record yet.');
        }

        // The latest check per file (the log is append-only, so the highest id).
        $fileIds = $files->modelKeys();
        $fixity = FixityCheck::query()
            ->whereIn('id', FixityCheck::query()->selectRaw('max(id)')->whereIn('record_file_id', $fileIds)->groupBy('record_file_id'))
            ->get(['id', 'record_file_id', 'algorithm', 'matched', 'checked_at'])
            ->keyBy('record_file_id');

        return ToolResult::found([
            'files' => $files->map(function (RecordFile $file) use ($fixity): array {
                $technical = $file->technicalMetadata;
                $check = $fixity->get($file->id);

                return [
                    'role' => $file->role->value,
                    'original_filename' => $file->original_filename,
                    'mime_type' => $file->mime_type,
                    'file_size_bytes' => $file->file_size,
                    'checksum_status' => $file->checksum_status->value,
                    'sha256' => $file->sha256_checksum,
                    'md5' => $file->md5_checksum,
                    'latest_fixity_check' => $check === null ? null : [
                        'algorithm' => $check->algorithm,
                        'matched' => $check->matched,
                        'checked_at' => $check->checked_at->toIso8601String(),
                    ],
                    'technical' => $technical === null ? null : array_filter([
                        'format' => $technical->file_format,
                        'duration_seconds' => $technical->duration,
                        'bitrate' => $technical->bitrate,
                        'start_timecode' => $technical->start_timecode,
                        'video_codec' => $technical->video_codec,
                        'video_profile' => $technical->video_profile,
                        'resolution' => $technical->resolution,
                        'frame_rate' => $technical->frame_rate,
                        'aspect_ratio' => $technical->aspect_ratio,
                        'scan_type' => $technical->scan_type?->value,
                        'bit_depth' => $technical->bit_depth,
                        'chroma_subsampling' => $technical->chroma_subsampling,
                        'color_space' => $technical->color_space,
                        'audio_codec' => $technical->audio_codec,
                        'audio_channels' => $technical->audio_channels,
                        'audio_sample_rate' => $technical->audio_sample_rate,
                    ], fn (mixed $value): bool => $value !== null),
                ];
            })->values()->all(),
        ]);
    }
}
