<?php

namespace App\Services\YouTube;

use App\Models\YoutubePublication;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The few YouTube Data API v3 calls publishing needs, over plain HTTP: the
 * signed-in channel, a resumable video upload (start, send a chunk, ask how
 * far it got), the custom thumbnail, and the video's upload and processing
 * status. Callers pass a valid access token (YouTubeOAuth::accessToken).
 *
 * @phpstan-type UploadProgress array{done: bool, received: int, video_id: string|null, upload_status: string|null}
 */
class YouTubeClient
{
    public const string API_URL = 'https://www.googleapis.com/youtube/v3';

    public const string UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3';

    /**
     * Seconds one chunk may take; the publish step's time budget assumes it.
     */
    public const int CHUNK_TIMEOUT_SECONDS = 25;

    /**
     * @return array{id: string, title: string, thumbnail_url: string|null}
     */
    public function myChannel(string $accessToken): array
    {
        $response = $this->send(fn () => $this->api($accessToken)->get(self::API_URL.'/channels', ['part' => 'snippet', 'mine' => 'true']), 'read the YouTube channel');
        $channel = $response->json('items.0');

        if (! is_array($channel) || ! is_string($channel['id'] ?? null)) {
            throw new YouTubeException('The Google account has no YouTube channel yet. Create one on YouTube, then connect again.');
        }

        $thumbnail = $response->json('items.0.snippet.thumbnails.default.url');

        return [
            'id' => $channel['id'],
            'title' => mb_substr((string) ($response->json('items.0.snippet.title') ?: 'YouTube channel'), 0, 200),
            'thumbnail_url' => is_string($thumbnail) && str_starts_with($thumbnail, 'https://') ? mb_substr($thumbnail, 0, 2048) : null,
        ];
    }

    /**
     * Open a resumable upload session with the video's title, description,
     * tags, category and privacy; returns the session URL to send bytes to.
     */
    public function startUpload(string $accessToken, YoutubePublication $publication, int $size, string $mimeType): string
    {
        $response = $this->send(fn () => $this->api($accessToken)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $size,
                'X-Upload-Content-Type' => $mimeType,
            ])
            ->withQueryParameters(['uploadType' => 'resumable', 'part' => 'snippet,status'])
            ->post(self::UPLOAD_URL.'/videos', [
                'snippet' => [
                    'title' => $publication->title,
                    'description' => $publication->description,
                    'tags' => $publication->tags,
                    'categoryId' => $publication->category_id,
                ],
                'status' => [
                    'privacyStatus' => $publication->privacy_status->value,
                    'selfDeclaredMadeForKids' => $publication->made_for_kids,
                ],
            ]), 'start the YouTube upload');

        $location = $response->header('Location');

        if (! str_starts_with($location, self::UPLOAD_URL.'/')) {
            throw new YouTubeException('YouTube did not return an upload address.', transient: true);
        }

        return $location;
    }

    /**
     * Send the bytes that start at `$offset`. YouTube answers 308 with how
     * much it has so far, or 200/201 with the new video once it has it all.
     *
     * @return UploadProgress
     */
    public function uploadChunk(string $accessToken, string $sessionUrl, string $bytes, int $offset, int $total, string $mimeType): array
    {
        $end = $offset + strlen($bytes) - 1;

        $response = $this->send(fn () => $this->upload($accessToken)
            ->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$total}"])
            ->withBody($bytes, $mimeType)
            ->put($sessionUrl), 'upload to YouTube');

        return $this->progress($response, $offset);
    }

    /**
     * How many bytes YouTube already has (after an interruption).
     *
     * @return UploadProgress
     */
    public function uploadStatus(string $accessToken, string $sessionUrl, int $total): array
    {
        $response = $this->send(fn () => $this->upload($accessToken)
            ->withHeaders(['Content-Range' => "bytes */{$total}"])
            ->withBody('', 'application/octet-stream')
            ->put($sessionUrl), 'resume the YouTube upload');

        return $this->progress($response, 0);
    }

    /**
     * Upload the custom thumbnail (JPEG or PNG, at most 2 MB). YouTube allows
     * it only on channels that verified a phone number.
     */
    public function setThumbnail(string $accessToken, string $videoId, string $image, string $mimeType): void
    {
        $this->send(fn () => $this->api($accessToken)
            ->timeout(20)
            ->withQueryParameters(['videoId' => $videoId, 'uploadType' => 'media'])
            ->withBody($image, $mimeType)
            ->post(self::UPLOAD_URL.'/thumbnails/set'), 'set the YouTube thumbnail');
    }

    /**
     * The video's upload status (`uploaded`, `processed`, `failed`,
     * `rejected`, `deleted`), privacy, processing status and the reason for a
     * failure or rejection; null when YouTube doesn't list the video.
     *
     * @return array{upload_status: string|null, privacy_status: string|null, processing_status: string|null, reason: string|null}|null
     */
    public function videoStatus(string $accessToken, string $videoId): ?array
    {
        $response = $this->send(fn () => $this->api($accessToken)->get(self::API_URL.'/videos', [
            'part' => 'status,processingDetails',
            'id' => $videoId,
        ]), 'read the video\'s status on YouTube');

        if (! is_array($response->json('items.0'))) {
            return null;
        }

        $reason = $response->json('items.0.status.rejectionReason') ?? $response->json('items.0.status.failureReason');

        return [
            'upload_status' => $this->string($response->json('items.0.status.uploadStatus')),
            'privacy_status' => $this->string($response->json('items.0.status.privacyStatus')),
            'processing_status' => $this->string($response->json('items.0.processingDetails.processingStatus')),
            'reason' => $this->string($reason),
        ];
    }

    /**
     * @return UploadProgress
     */
    private function progress(Response $response, int $offset): array
    {
        if ($response->status() === 308) {
            // "Range: bytes=0-N" = N + 1 bytes received; no header = none yet.
            $received = preg_match('/bytes=0-(\d+)/', $response->header('Range'), $match) === 1 ? (int) $match[1] + 1 : 0;

            return ['done' => false, 'received' => $received, 'video_id' => null, 'upload_status' => null];
        }

        $videoId = $response->json('id');

        if (! is_string($videoId) || $videoId === '') {
            throw new YouTubeException('YouTube finished the upload without a video id.');
        }

        return ['done' => true, 'received' => $offset, 'video_id' => $videoId, 'upload_status' => $this->string($response->json('status.uploadStatus'))];
    }

    /**
     * Run a call; network failures and error answers become YouTubeExceptions.
     * A 308 (upload incomplete) is a normal answer.
     *
     * @param  callable(): Response  $call
     */
    private function send(callable $call, string $action): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw new YouTubeException("Could not reach YouTube to {$action}.", transient: true);
        }

        if ($response->status() === 404 && str_contains($action, 'upload')) {
            throw new YouTubeException('The YouTube upload session expired.', reason: 'sessionExpired');
        }

        if ($response->failed()) {
            throw YouTubeException::fromResponse($response, $action);
        }

        return $response;
    }

    private function api(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson()->timeout(15)->connectTimeout(10);
    }

    /**
     * Upload requests: no redirects, since YouTube's "308 Resume Incomplete"
     * is an answer, not a redirect.
     */
    private function upload(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson()->withoutRedirecting()->timeout(self::CHUNK_TIMEOUT_SECONDS)->connectTimeout(10);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 32) : null;
    }
}
