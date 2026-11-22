<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Models\RecordContributor;
use App\Models\RecordKeyword;
use App\Models\RecordSubject;
use App\Services\Chat\ChatScope;

/**
 * The archival catalogue: credits, subjects and keywords, rights, provenance,
 * classification and retention, and the work (reels/parts) it belongs to.
 * Attachments (release forms) are never listed.
 */
class GetArchivalDetailsTool extends RecordTool
{
    private const int MAX_ITEMS = 40;

    public function name(): string
    {
        return 'get_archival_details';
    }

    public function description(): string
    {
        return 'The archival catalogue of this record: credits (contributors by role), subjects and keywords '
            .'(confirmed or still AI suggestions), rights (copyright, licence, access level, restrictions, '
            .'embargo, permission, release consent), provenance (source carrier, transfer), security '
            .'classification, retention and disposition, and the work/reels it belongs to.';
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
            'contributors:id,record_id,role,name,sort_order',
            'subjects' => fn ($query) => $query->select(['id', 'record_id', 'subject', 'type', 'is_confirmed'])->limit(self::MAX_ITEMS),
            'keywords' => fn ($query) => $query->select(['id', 'record_id', 'keyword', 'is_confirmed'])->limit(self::MAX_ITEMS),
            'rights',
            'provenance',
            'work:id,title,total_parts',
        ]);
        $rights = $record->rights;
        $provenance = $record->provenance;
        $credits = $record->contributors->take(self::MAX_ITEMS)->map(fn (RecordContributor $contributor): array => [
            'role' => $contributor->role->label(),
            'name' => $contributor->name,
        ])->values()->all();

        if (! $internal) {
            // What the public watch page shows: credits, confirmed subjects and keywords, licence.
            return ToolResult::found([
                'credits' => $credits,
                'subjects' => $record->subjects->where('is_confirmed', true)->map(fn (RecordSubject $subject): array => ['subject' => $subject->subject, 'type' => $subject->type->value])->values()->all(),
                'keywords' => $record->keywords->where('is_confirmed', true)->pluck('keyword')->values()->all(),
                'license' => $rights?->license,
                'license_url' => $rights?->license_url,
                'usage_restrictions' => $rights?->usage_restrictions,
            ]);
        }

        return ToolResult::found([
            'accession_number' => $record->accession_number,
            'credits' => $credits,
            'subjects' => $record->subjects->map(fn (RecordSubject $subject): array => [
                'subject' => $subject->subject,
                'type' => $subject->type->value,
                'confirmed' => $subject->is_confirmed,
            ])->values()->all(),
            'keywords' => $record->keywords->map(fn (RecordKeyword $keyword): array => [
                'keyword' => $keyword->keyword,
                'confirmed' => $keyword->is_confirmed,
            ])->values()->all(),
            'rights' => $rights === null ? null : [
                'copyright_holder' => $rights->copyright_holder,
                'copyright_year' => $rights->copyright_year,
                'rights_statement' => $rights->rights_statement,
                'license' => $rights->license,
                'access_level' => $rights->access_level->value,
                'usage_restrictions' => $rights->usage_restrictions,
                'territories' => $rights->territories ?? [],
                'embargo_until' => $rights->embargo_until?->toDateString(),
                'embargoed_now' => $rights->isEmbargoed(),
                'permission_status' => $rights->permission_status->value,
                'release_consent' => $rights->release_consent->value,
            ],
            'provenance' => $provenance === null ? null : [
                'source_carrier' => $provenance->source_carrier?->value,
                'carrier_label' => $provenance->carrier_label,
                'transfer_date' => $provenance->transfer_date?->toDateString(),
                'transfer_facility' => $provenance->transfer_facility,
                'transfer_operator' => $provenance->transfer_operator,
                'notes' => $provenance->notes,
            ],
            'security_classification' => $record->security_classification->value,
            'retention_period' => $record->retention_period,
            'archival_value' => $record->archival_value?->value,
            'disposition_date' => $record->disposition_date?->toDateString(),
            'disposition_action' => $record->disposition_action?->value,
            'work' => $record->work === null ? null : [
                'title' => $record->work->title,
                'total_parts' => $record->work->total_parts,
                'this_part' => $record->part_label ?? $record->part_number,
            ],
        ]);
    }
}
