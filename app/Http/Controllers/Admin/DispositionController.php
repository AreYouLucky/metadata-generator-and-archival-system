<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DispositionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexDispositionsRequest;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DispositionController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Disposition review (PROJECT_SPEC §11): records whose retention period has
     * ended (disposition date today or earlier), oldest first. Nothing is
     * destroyed automatically; an admin decides what happens to each.
     */
    public function __invoke(IndexDispositionsRequest $request): Response
    {
        $action = $request->validated('action');

        $records = Record::query()
            ->select(['id', 'created_by', 'title', 'accession_number', 'security_classification', 'retention_period', 'disposition_date', 'disposition_action'])
            ->whereNotNull('disposition_date')
            ->whereDate('disposition_date', '<=', today())
            ->when($action, fn (Builder $query, string $value) => $query->where('disposition_action', $value))
            ->with('creator:id,name')
            ->orderBy('disposition_date')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Record $record): array => [
                'id' => $record->id,
                'title' => $record->title,
                'accession_number' => $record->accession_number,
                'classification' => $record->security_classification->value,
                'retention_period' => $record->retention_period,
                'disposition_date' => $record->disposition_date?->toDateString(),
                'disposition_action' => $record->disposition_action?->value,
                'creator' => $record->creator?->name,
            ]);

        return Inertia::render('admin/dispositions-page/dispositions', [
            'records' => $records,
            'filters' => ['action' => $action],
            'actions' => DispositionAction::options(),
        ]);
    }
}
