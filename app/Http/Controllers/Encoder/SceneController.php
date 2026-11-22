<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\SceneKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\DestroySceneRequest;
use App\Http\Requests\Encoder\StoreSceneRequest;
use App\Http\Requests\Encoder\UpdateSceneRequest;
use App\Models\Record;
use App\Models\RecordScene;
use App\Services\Records\SceneStore;
use App\Services\Records\VisualsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Scenes are named groups of shots made by encoders (PROJECT_SPEC §11). The
 * shots themselves come from the `scenes` stage and can't be changed here.
 */
class SceneController extends Controller
{
    public function store(StoreSceneRequest $request, Record $record, SceneStore $scenes): JsonResponse
    {
        $scene = $scenes->createScene($record, $request->scene());

        return response()->json(VisualsPresenter::scene($scene), 201);
    }

    public function update(UpdateSceneRequest $request, Record $record, RecordScene $scene, SceneStore $scenes): JsonResponse
    {
        $this->ensureNamedScene($scene);

        return response()->json(VisualsPresenter::scene($scenes->updateScene($record, $scene, $request->scene())));
    }

    public function destroy(DestroySceneRequest $request, Record $record, RecordScene $scene): Response
    {
        $this->ensureNamedScene($scene);
        $scene->delete();

        return response()->noContent();
    }

    private function ensureNamedScene(RecordScene $scene): void
    {
        abort_unless($scene->kind === SceneKind::Scene, 422, 'Detected shots can\'t be changed; group them into a scene instead.');
    }
}
