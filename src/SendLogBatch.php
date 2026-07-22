<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Delivers a normalized batch to OpenLogs from a queue worker.
 *
 * handle() POSTs the batch and throws on failure so the queue's retry/backoff
 * applies. Once retries are exhausted, failed() replays the entries to the
 * configured fallback Laravel channel so no logs are lost.
 */
final class SendLogBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param array<int, array<string, mixed>>                                    $entries
     * @param array{url: string, api_key: string, timeout: float, fallback_channel: ?string} $config
     */
    public function __construct(
        public readonly array $entries,
        public readonly array $config,
    ) {
    }

    public function handle(): void
    {
        if ($this->entries === []) {
            return;
        }

        $response = $this->client()->request('POST', $this->endpoint(), [
            'headers' => [
                'X-API-Key'    => $this->config['api_key'],
                'Content-Type' => 'application/json',
            ],
            'body'        => (string) json_encode($this->entries),
            'timeout'     => $this->config['timeout'] ?? 5.0,
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new RuntimeException('openlogs: batch delivery returned status ' . $response->getStatusCode());
        }
    }

    public function failed(?Throwable $e): void
    {
        $channel = $this->config['fallback_channel'] ?? null;
        if (! is_string($channel) || $channel === '') {
            return;
        }

        $log = Log::channel($channel);
        foreach ($this->entries as $entry) {
            $log->log(
                strtolower((string) ($entry['level_name'] ?? 'error')),
                (string) ($entry['message'] ?? ''),
                is_array($entry['context'] ?? null) ? $entry['context'] : [],
            );
        }
    }

    private function client(): ClientInterface
    {
        // Prefer a container-bound client (tests bind a mock); otherwise a default.
        if (function_exists('app') && app()->bound(ClientInterface::class)) {
            /** @var ClientInterface $client */
            $client = app(ClientInterface::class);

            return $client;
        }

        return new Client();
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->config['url'], '/') . '/api/ingest/batch';
    }
}
