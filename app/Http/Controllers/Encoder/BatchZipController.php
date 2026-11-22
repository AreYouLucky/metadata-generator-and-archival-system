<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\BatchZipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\BuildBatchZipRequest;
use App\Jobs\BuildBatchZipJob;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchZipController extends Controller
{
    /**
     * Build (or rebuild) the ZIP in the background; the batch page hears when
     * it is ready. A build already under way isn't started twice.
     */
    public function store(BuildBatchZipRequest $request, Batch $batch): JsonResponse
    {
        $started = DB::transaction(function () use ($batch): bool {
            $locked = Batch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail(['id', 'zip_status']);

            if ($locked->zip_status === BatchZipStatus::Building) {
                return false;
            }

            $batch->zip_status = BatchZipStatus::Building;
            $batch->save();

            return true;
        });

        if ($started) {
            BuildBatchZipJob::dispatch($batch, $request->boolean('thumbnails'), $request->user()?->id)->afterCommit();
        }

        return response()->json(['zip_status' => BatchZipStatus::Building->value], 202);
    }

    /**
     * Download the finished ZIP (PROJECT_SPEC §8, GET /batches/{batch}/download).
     */
    public function show(Batch $batch): StreamedResponse
    {
        Gate::authorize('download', $batch);

        $disk = Storage::disk('media');

        abort_unless($batch->zip_status === BatchZipStatus::Ready && $batch->zip_path !== null && $disk->exists($batch->zip_path), 404, 'The ZIP has not been built yet.');

        return $disk->download($batch->zip_path, (Str::slug($batch->label()) ?: 'batch').'.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
