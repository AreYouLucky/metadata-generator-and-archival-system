<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\SaveRightsRequest;
use App\Models\Record;
use App\Services\Records\ArchivePresenter;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;

class RightsController extends Controller
{
    /**
     * Save the rights and access metadata. They feed the distribution policy
     * (access level, permission, embargo), so every change is logged.
     */
    public function __invoke(SaveRightsRequest $request, Record $record, AuditTrail $audit, ArchivePresenter $archive): JsonResponse
    {
        $rights = $record->rights()->firstOrNew();
        $rights->fill($request->validated());
        $changed = array_keys($rights->getDirty());

        if (! $rights->exists || $changed !== []) {
            $record->rights()->save($rights);
            $audit->log($record, AuditAction::Updated, $request->user(), 'Rights changed: '.implode(', ', $changed));
        }

        return response()->json(['archive' => $archive->present($record->unsetRelation('rights'))]);
    }
}
