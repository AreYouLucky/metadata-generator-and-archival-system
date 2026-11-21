<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordWaveformController extends Controller
{
    /**
     * Waveform peaks for the workspace timeline, written by the AI service's
     * extract_audio stage to `records/{id}/waveform.json`. The path is fixed by
     * the record id, never taken from the request.
     */
    public function __invoke(Record $record): BinaryFileResponse
    {
        Gate::authorize('view', $record);

        $disk = Storage::disk('media');
        $path = self::path($record);

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function path(Record $record): string
    {
        return "records/{$record->id}/waveform.json";
    }
}
