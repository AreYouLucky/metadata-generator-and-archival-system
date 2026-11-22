<?php

namespace App\Jobs;

use App\Models\Record;
use App\Rules\WebhookUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Tells a public-API client that its record finished processing
 * (`record.ready` or `record.failed`): a JSON POST to the record's
 * `webhook_url`, signed like the internal calls:
 * `X-Archive-Signature: sha256=HMAC_SHA256(owner's webhook secret, timestamp.body)`
 * with the timestamp in `X-Archive-Timestamp`. The host is resolved and
 * checked again here, and the request is pinned to the address that was
 * checked, without following redirects, so a webhook can't be pointed into
 * the private network. Outages and 5xx/429 answers are retried with backoff.
 */
class SendRecordWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Record $record, public string $event) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $record = $this->record->fresh(['creator:id,webhook_secret']);
        $url = $record?->webhook_url;
        $secret = $record?->creator?->webhook_secret;

        if ($record === null || $url === null || $secret === null || Validator::make(['url' => $url], ['url' => [new WebhookUrl]])->fails()) {
            return;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $address = $this->publicAddress($host);

        if ($address === null) {
            Log::warning('Webhook not sent: the host does not resolve to a public address', ['record_id' => $record->id]);

            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'record' => [
                'id' => $record->id,
                'title' => $record->title,
                'accession_number' => $record->accession_number,
                'processing_status' => $record->processing_status->value,
                'processing_error' => $record->processing_error,
                'review_status' => $record->review_status->value,
                'url' => route('api.v1.records.show', $record),
            ],
            'sent_at' => now()->utc()->toIso8601ZuluString(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->getTimestamp();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'ArchiveWebhook/1.0',
                'X-Archive-Event' => $this->event,
                'X-Archive-Timestamp' => $timestamp,
                'X-Archive-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
            ])
                ->withBody($body, 'application/json')
                ->withoutRedirecting()
                ->timeout((int) config('archive.api.webhook_timeout_seconds'))
                ->connectTimeout(5)
                // Connect to the address that was checked, not whatever the name resolves to next.
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]]])
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The webhook could not be reached.', previous: $exception);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new RuntimeException("The webhook answered {$response->status()}.");
        }

        if (! $response->successful()) {
            Log::info('Webhook refused', ['record_id' => $record->id, 'status' => $response->status()]);

            return;
        }

        $record->forceFill(['webhook_sent_at' => now()])->saveQuietly();
    }

    /**
     * One of the host's addresses, only when every address it resolves to is
     * public; null otherwise.
     */
    private function publicAddress(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $addresses = array_values(array_filter(array_map(fn (array $entry): ?string => $entry['ip'] ?? $entry['ipv6'] ?? null, $records)));
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        $address = $addresses[0] ?? null;

        return $address !== null && str_contains($address, ':') ? "[{$address}]" : $address;
    }
}
