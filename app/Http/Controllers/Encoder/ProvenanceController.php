<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SaveProvenanceRequest;
use App\Models\Record;
use App\Services\Records\ArchivePresenter;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;

class ProvenanceController extends Controller
{
    /**
     * Save the source carrier and transfer details.
     */
    public function __invoke(SaveProvenanceRequest $request, Record $record, AuditTrail $audit, ArchivePresenter $archive): JsonResponse
    {
        $provenance = $record->provenance()->firstOrNew();
        $provenance->fill($request->validated());
        $changed = array_keys($provenance->getDirty());

        if ($changed !== []) {
            $record->provenance()->save($provenance);
            $audit->log($record, AuditAction::Updated, $request->user(), 'Provenance changed: '.implode(', ', $changed));
        }

        return response()->json(['archive' => $archive->present($record->unsetRelation('provenance'))]);
    }
}
