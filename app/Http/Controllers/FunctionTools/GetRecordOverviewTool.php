<?php

namespace App\Http\Controllers\FunctionTools;

use App\Enums\SceneKind;
use App\Models\Record;
use App\Services\Chat\ChatScope;

/**
 * One record's catalogue basics and which kinds of data it has, so the model
 * knows where to look and can say what is missing. Public viewers get the
 * fields of the public watch page; staff who may open the record also get
 * the accession number and processing details.
 */
class GetRecordOverviewTool extends RecordTool
{
    public function name(): string
    {
        return 'get_record_overview';
    }

    public function description(): string
    {
        return 'Catalogue basics of one record (title, description, event/project, series, agency, genre, video '
            .'type, language, dates, place, duration, licence) and which data it has (transcript, speakers, key '
            .'frames, scenes). Call it first when unsure where an answer could be.';
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
        $record->loadMissing([
            'project:id,title,date,end_date',
            'projectSeries:id,title',
            'agency:id,name,acronym',
            'division:id,name',
            'genre:id,name',
            'videoType:id,name',
            'region:id,name',
            'province:id,name',
            'city:id,name',
            'barangay:id,name',
            'rights',
            'masterFile:id,record_id,role',
            'masterFile.technicalMetadata:id,record_file_id,duration',
        ]);
        $record->loadCount([
            'speakers',
            'frames',
            'scenes as named_scenes_count' => fn ($query) => $query->where('kind', SceneKind::Scene),
        ]);
        $transcript = $record->originalTranscript()->withCount('segments')->first(['id', 'record_id', 'language']);
        $duration = $record->masterFile?->technicalMetadata?->duration;

        $data = [
            'record_id' => $record->id,
            'title' => $record->title,
            'alternative_title' => $record->alternative_title,
            'description' => $record->description,
            'event' => $record->project === null ? null : [
                'title' => $record->project->title,
                'date' => $record->project->date?->toDateString(),
                'end_date' => $record->project->end_date?->toDateString(),
            ],
            'series' => $record->projectSeries?->title,
            'agency' => $record->agency === null ? null : trim($record->agency->name.($record->agency->acronym ? " ({$record->agency->acronym})" : '')),
            'production_unit' => $record->division?->name,
            'genre' => $record->genre?->name,
            'video_type' => $record->videoType?->name,
            'language' => $record->language,
            'date_recorded' => $record->date_recorded?->toDateString(),
            'date_recorded_precision' => $record->date_recorded_precision?->value,
            'published_at' => $record->published_at?->toDateString(),
            'place' => $record->placeLabel(),
            'location' => $record->location,
            'duration_seconds' => $duration === null ? null : round($duration, 1),
            'license' => $record->rights?->license,
            'usage_restrictions' => $record->rights?->usage_restrictions,
            'available_data' => [
                'transcript_lines' => (int) ($transcript->segments_count ?? 0),
                'transcript_language' => $transcript?->language,
                'speakers' => (int) $record->speakers_count,
                'key_frames' => (int) $record->frames_count,
                'named_scenes' => (int) $record->getAttribute('named_scenes_count'),
            ],
            'access' => $internal ? 'full' : 'public',
        ];

        if ($internal) {
            $data += [
                'accession_number' => $record->accession_number,
                'country_of_origin' => $record->country_origin,
                'season' => $record->season_number,
                'episode' => $record->episode_number,
                'security_classification' => $record->security_classification->value,
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'ai_metadata' => $record->activeMetadata()->exists(),
            ];
        }

        return ToolResult::found(array_filter($data, fn (mixed $value): bool => $value !== null));
    }
}
