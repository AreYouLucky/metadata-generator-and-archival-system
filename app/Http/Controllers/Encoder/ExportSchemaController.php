<?php

namespace App\Http\Controllers\Encoder;

use App\Exporters\ExportSchemaFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreExportSchemaRequest;
use App\Http\Requests\Encoder\UpdateExportSchemaRequest;
use App\Models\ExportSchema;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Export schemas: each user's own custom JSON exports.
 */
class ExportSchemaController extends Controller
{
    /**
     * A user has a handful of schemas at most, so the list isn't paginated;
     * it is capped instead (MAX_SCHEMAS).
     */
    public const int MAX_SCHEMAS = 50;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExportSchema::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('encoder/settings/export-schemas-page/export-schemas', [
            'schemas' => self::list($user),
            'fieldGroups' => ExportSchemaFields::options(),
        ]);
    }

    public function store(StoreExportSchemaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->exportSchemas()->count() >= self::MAX_SCHEMAS, 422, 'You have reached the limit of '.self::MAX_SCHEMAS.' export schemas.');

        $schema = DB::transaction(function () use ($user, $request): ExportSchema {
            $schema = $user->exportSchemas()->make($request->validated());
            $this->claimDefault($user, $schema);
            $schema->save();

            return $schema;
        });

        return response()->json(['schema' => self::present($schema)], 201);
    }

    public function update(UpdateExportSchemaRequest $request, ExportSchema $exportSchema): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $exportSchema): void {
            $exportSchema->fill($request->validated());
            $this->claimDefault($user, $exportSchema);
            $exportSchema->save();
        });

        return response()->json(['schema' => self::present($exportSchema)]);
    }

    public function destroy(ExportSchema $exportSchema): JsonResponse
    {
        Gate::authorize('delete', $exportSchema);

        $exportSchema->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * The user's schemas for the settings page and the workspace's picker.
     *
     * @return list<array{id: int, name: string, fields: list<string>, is_default: bool}>
     */
    public static function list(User $user): array
    {
        return array_values($user->exportSchemas()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->limit(self::MAX_SCHEMAS)
            ->get(['id', 'user_id', 'name', 'fields', 'is_default'])
            ->map(fn (ExportSchema $schema): array => self::present($schema))
            ->all());
    }

    /**
     * @return array{id: int, name: string, fields: list<string>, is_default: bool}
     */
    private static function present(ExportSchema $schema): array
    {
        return [
            'id' => $schema->id,
            'name' => $schema->name,
            'fields' => $schema->fields,
            'is_default' => $schema->is_default,
        ];
    }

    /**
     * Only one default per user: making this one the default clears the others.
     */
    private function claimDefault(User $user, ExportSchema $schema): void
    {
        if ($schema->is_default) {
            $user->exportSchemas()
                ->where('is_default', true)
                ->when($schema->exists, fn ($query) => $query->whereKeyNot($schema->id))
                ->update(['is_default' => false]);
        }
    }
}
