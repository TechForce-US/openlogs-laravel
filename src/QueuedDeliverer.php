<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel;

use Illuminate\Contracts\Bus\Dispatcher;
use TechForce\OpenLogs\BatchDeliverer;

/**
 * A BatchDeliverer that hands delivery to a background job instead of POSTing
 * inline. Only the already-normalized wire entries are queued (never Monolog
 * record objects), so the payload serializes cheaply. The job targets a
 * dedicated connection/queue.
 */
final class QueuedDeliverer implements BatchDeliverer
{
    /**
     * @param array{url: string, api_key: string, timeout: float, fallback_channel: ?string} $config
     */
    public function __construct(
        private readonly Dispatcher $bus,
        private readonly array $config,
        private readonly ?string $connection,
        private readonly string $queue,
    ) {
    }

    public function deliver(array $entries, array $records): void
    {
        if ($entries === []) {
            return;
        }

        $job = (new SendLogBatch(array_values($entries), $this->config))
            ->onConnection($this->connection)
            ->onQueue($this->queue);

        $this->bus->dispatch($job);
    }
}
