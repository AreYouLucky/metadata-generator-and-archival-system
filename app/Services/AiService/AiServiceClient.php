<?php

namespace App\Services\AiService;

use App\Services\InternalSignature;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Typed HTTP client for the Python AI service. Every request is HMAC-signed
 * over its exact raw body (see InternalSignature).
 */
class AiServiceClient
{
    public function __construct(
        private InternalSignature $signature,
        #[Config('archive.ai_service.url')] private string $baseUrl,
        #[Config('archive.ai_service.timeout_seconds')] private int $timeoutSeconds = 10,
    ) {}

    /**
     * Never throws: an unreachable or unhealthy service is reported in the result.
     * `gpu` / `gpu_name` describe the transcription worker's GPU, not the API's.
     *
     * `transcription_provider` / `diarization_provider` say which providers
     * hear the audio (local, openai, assemblyai, pyannote).
     *
     * @return array{reachable: bool, status: string|null, version: string|null, gpu: bool|null, gpu_name: string|null, transcription_provider: string|null, diarization_provider: string|null, latency_ms: int|null, error: string|null}
     */
    public function health(): array
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->send('GET', '/health')->throw();
        } catch (ConnectionException) {
            return $this->unreachable('The AI service could not be reached.');
        } catch (RequestException $exception) {
            return $this->unreachable("The AI service answered with HTTP {$exception->response->status()}.");
        }

        return [
            'reachable' => true,
            'status' => is_string($response->json('status')) ? $response->json('status') : null,
            'version' => is_string($response->json('version')) ? $response->json('version') : null,
            'gpu' => is_bool($response->json('gpu')) ? $response->json('gpu') : null,
            'gpu_name' => is_string($response->json('gpu_name')) ? $response->json('gpu_name') : null,
            'transcription_provider' => is_string($response->json('transcription_provider')) ? $response->json('transcription_provider') : null,
            'diarization_provider' => is_string($response->json('diarization_provider')) ? $response->json('diarization_provider') : null,
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'error' => null,
        ];
    }

    /**
     * Ask the AI service to call Laravel back on the signed /internal/ping
     * route from its Celery worker. Returns the worker job id.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function ping(string $pingId): string
    {
        return (string) $this->send('POST', '/ping', ['ping_id' => $pingId])->throw()->json('job_id');
    }

    /**
     * Queue the processing pipeline for a record's master file. Returns the job id;
     * results arrive later on the signed /internal callbacks.
     *
     * @param  array{record_id: string, record_file_id: string, video_path: string, options: array<string, mixed>, seed?: array<string, mixed>}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function process(array $payload): string
    {
        return (string) $this->send('POST', '/jobs/process', $payload)->throw()->json('job_id');
    }

    /**
     * Download a web address into the record's master folder (yt-dlp).
     * Returns the job id, which also cancels the download; progress arrives
     * on /internal/records/{record}/progress (stage `import`) and the file
     * on /internal/imports/{record}/done.
     *
     * @param  array{record_id: string, record_file_id: string, url: string, output_dir: string, max_bytes: int, max_duration_seconds: int}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function importUrl(array $payload): string
    {
        return (string) $this->send('POST', '/import-url', $payload)->throw()->json('job_id');
    }

    /**
     * Stop a pipeline run (the id `process()` returned). The id is repeated in
     * the body because only the body is signed. Idempotent.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function cancel(string $jobId): void
    {
        $this->send('POST', '/jobs/'.rawurlencode($jobId).'/cancel', ['job_id' => $jobId])->throw();
    }

    /**
     * Generate AI metadata synchronously (used by GenerateMetadataJob for regenerations).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function generateMetadata(array $payload): array
    {
        /** @var array<string, mixed> */
        return $this->send('POST', '/generate/metadata', $payload, timeoutSeconds: 300)->throw()->json();
    }

    /**
     * More title options from the active generation's summary (GenerateTitlesJob).
     *
     * @param  array<string, mixed>  $payload
     * @return array{model?: string, titles?: list<string>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function generateTitles(array $payload): array
    {
        /** @var array{model?: string, titles?: list<string>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>} */
        return $this->send('POST', '/generate/titles', $payload, timeoutSeconds: 120)->throw()->json();
    }

    /**
     * Catalogue field suggestions for the Archive panel ("Fill with AI").
     *
     * @param  array<string, mixed>  $payload
     * @return array{model?: string, fields?: array<string, mixed>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function suggestDescriptive(array $payload): array
    {
        /** @var array{model?: string, fields?: array<string, mixed>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>} */
        return $this->send('POST', '/generate/descriptive', $payload, timeoutSeconds: 120)->throw()->json();
    }

    /**
     * One step of the chat tool loop (Phase 7): the model asks for tool calls
     * or answers. The AI service never reads the database; the tools run here.
     *
     * @param  array<string, mixed>  $payload
     * @return array{type?: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, answer?: array{answer: string, grounded: bool, citations: list<array{start: float, end: float}>}|null, model?: string, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function chat(array $payload): array
    {
        /** @var array{type?: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, answer?: array{answer: string, grounded: bool, citations: list<array{start: float, end: float}>}|null, model?: string, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>} */
        return $this->send('POST', '/chat', $payload, timeoutSeconds: 120)->throw()->json();
    }

    /**
     * Embeddings for transcript passages or a chat question (Phase 7).
     *
     * @param  list<string>  $texts
     * @return array{model?: string, dimensions?: int, vectors?: list<list<float>>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function embed(array $texts, int $dimensions, ?string $recordId = null): array
    {
        /** @var array{model?: string, dimensions?: int, vectors?: list<list<float>>, usage?: list<array{kind: string, quantity: float, estimated_cost?: float|null}>} */
        return $this->send('POST', '/embed', [
            'record_id' => $recordId,
            'texts' => $texts,
            'dimensions' => $dimensions,
        ], timeoutSeconds: 60)->throw()->json();
    }

    /**
     * One frame of the master saved as the record's thumbnail ("use current
     * frame"). Synchronous: a single frame takes about a second. Returns the
     * stored path (relative to the media root).
     *
     * @param  array{record_id: string, video_path: string, time_sec: float, output_path: string}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function captureFrame(array $payload): string
    {
        return (string) $this->send('POST', '/frames/capture', $payload, timeoutSeconds: 30)->throw()->json('path');
    }

    /**
     * Export the master's audio as MP3 or WAV in the background. Returns the
     * job id; the result arrives on /internal/records/{record}/audio-export.
     *
     * @param  array{record_id: string, video_path: string, format: string, output_path: string, duration: float|null}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function exportAudio(array $payload): string
    {
        return (string) $this->send('POST', '/jobs/export-audio', $payload)->throw()->json('job_id');
    }

    /**
     * Cut a clip from the master (Phase 7): re-encoded to H.264/AAC MP4, then
     * checksummed and probed. Returns the job id; the result arrives on
     * /internal/records/{record}/clips/{clip}.
     *
     * @param  array{record_id: string, clip_id: int, video_path: string, start: float, end: float, output_path: string, max_height: int}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function cutClip(array $payload): string
    {
        return (string) $this->send('POST', '/clips', $payload)->throw()->json('job_id');
    }

    /**
     * Queue a transcript translation. Returns the job id; the lines arrive on
     * the signed /internal/records/{record}/translations/{transcript} callbacks.
     *
     * @param  array{record_id: string, transcript_id: int, source_language: string|null, target_language: string, target_language_name: string, lines: list<array{id: int, text: string}>}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function translate(array $payload): string
    {
        return (string) $this->send('POST', '/jobs/translate', $payload, timeoutSeconds: 30)->throw()->json('job_id');
    }

    /**
     * Checksum and probe an added file (mezzanine, access copy). Results arrive
     * on /internal/record-files/{file}/results/{checksum|probe|failed}.
     *
     * @param  array{record_id: string, record_file_id: string, path: string, role: string}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function ingestFile(array $payload): string
    {
        return (string) $this->send('POST', '/jobs/ingest-file', $payload)->throw()->json('job_id');
    }

    /**
     * Re-hash a stored file for a fixity check. The checksums arrive on
     * /internal/record-files/{file}/fixity; Laravel compares them.
     *
     * @param  array{record_file_id: string, path: string, triggered_by: string}  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function fixity(array $payload): string
    {
        return (string) $this->send('POST', '/jobs/fixity', $payload)->throw()->json('job_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    private function send(string $method, string $path, array $payload = [], ?int $timeoutSeconds = null): Response
    {
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $request = Http::baseUrl($this->baseUrl)
            ->timeout($timeoutSeconds ?? $this->timeoutSeconds)
            ->connectTimeout(3)
            ->acceptJson()
            ->withHeaders($this->signature->headers($body));

        if ($method !== 'GET') {
            $request = $request->withBody($body, 'application/json');
        }

        return $request->send($method, $path);
    }

    /**
     * @return array{reachable: false, status: null, version: null, gpu: null, gpu_name: null, transcription_provider: null, diarization_provider: null, latency_ms: null, error: string}
     */
    private function unreachable(string $error): array
    {
        return [
            'reachable' => false,
            'status' => null,
            'version' => null,
            'gpu' => null,
            'gpu_name' => null,
            'transcription_provider' => null,
            'diarization_provider' => null,
            'latency_ms' => null,
            'error' => $error,
        ];
    }
}
