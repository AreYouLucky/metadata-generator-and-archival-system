<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreProjectSeriesRequest;
use App\Http\Requests\Encoder\UpdateProjectSeriesRequest;
use App\Models\Project;
use App\Models\ProjectSeries;
use Illuminate\Http\JsonResponse;

class ProjectSeriesController extends Controller
{
    /**
     * Add a series (a session, day or episode run) to a project.
     */
    public function store(StoreProjectSeriesRequest $request, Project $project): JsonResponse
    {
        $series = $project->series()->create($request->validated());

        return response()->json([
            'id' => $series->id,
            'title' => $series->title,
            'date' => $series->date?->toDateString(),
            'end_date' => $series->end_date?->toDateString(),
        ], 201);
    }

    /**
     * Edit a series (allowed to whoever may edit its project).
     */
    public function update(UpdateProjectSeriesRequest $request, ProjectSeries $series): JsonResponse
    {
        $series->update($request->validated());

        return response()->json(['id' => $series->id, 'title' => $series->title]);
    }
}
