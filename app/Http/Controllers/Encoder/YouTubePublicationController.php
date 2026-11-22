<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\CheckYouTubeStatusRequest;
use App\Http\Requests\Encoder\PublishToYouTubeRequest;
use App\Jobs\CheckYouTubeStatusJob;
use App\Models\Record;
use App\Models\User;
use App\Models\YoutubeAccount;
use App\Models\YoutubePublication;
use App\Services\YouTube\PublishPanel;
use App\Services\YouTube\YouTubePublisher;
use Illuminate\Http\JsonResponse;

/**
 * Publishing a record to YouTube (Phase 7) from the workspace's Publish
 * panel. The upload runs in the background; progress arrives over Reverb
 * (`record.publication-updated`).
 */
class YouTubePublicationController extends Controller
{
    public function store(PublishToYouTubeRequest $request, Record $record, YouTubePublisher $publisher): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var YoutubeAccount $account */
        $account = $user->youtubeAccount()->firstOrFail();

        $publication = $publisher->publish($record, $user, $account, $request->choices());

        return response()->json(['publication' => PublishPanel::row($publication)], 202);
    }

    /**
     * Ask YouTube once more how the video is doing (after the automatic
     * checks stopped, or to see a change sooner).
     */
    public function status(CheckYouTubeStatusRequest $request, Record $record, YoutubePublication $youtubePublication): JsonResponse
    {
        abort_unless(in_array($youtubePublication->status, [PublicationStatus::Processing, PublicationStatus::Published], true), 422, 'Only an uploaded video has a status on YouTube.');

        CheckYouTubeStatusJob::dispatch($youtubePublication, (int) config('archive.youtube.status_checks'));

        return response()->json(['queued' => true], 202);
    }
}
