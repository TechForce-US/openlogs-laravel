<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel;

use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Monolog\Logger;
use TechForce\OpenLogs\BatchDeliverer;
use TechForce\OpenLogs\LoggerFactory;
use TechForce\OpenLogs\SyncGuzzleDeliverer;

/**
 * Laravel `custom` log channel factory. Referenced from config/logging.php:
 *
 *   'openlogs' => [
 *       'driver' => 'custom',
 *       'via'    => \TechForce\OpenLogs\Laravel\OpenLogsChannel::class,
 *   ],
 *
 * Builds the core OpenLogs handler, selecting synchronous (default) or queued
 * delivery from config, and wires the fallback channel with a circular guard.
 */
final class OpenLogsChannel
{
    public function __construct(private readonly Container $app)
    {
    }

    /**
     * @param array<string, mixed> $config The channel config merged by Laravel.
     */
    public function __invoke(array $config): Logger
    {
        $settings = array_merge(
            (array) $this->app->make('config')->get('openlogs', []),
            $config,
        );

        $channelName = (string) ($config['name'] ?? $config['channel'] ?? 'openlogs');
        $url = (string) ($settings['url'] ?? '');
        $apiKey = (string) ($settings['api_key'] ?? '');
        $level = $settings['level'] ?? 'debug';
        $bufferLimit = (int) ($settings['buffer_limit'] ?? LoggerFactory::DEFAULT_BUFFER_LIMIT);
        $timeout = (float) ($settings['timeout'] ?? 5.0);
        $queue = (array) ($settings['queue'] ?? []);
        $fallbackChannel = $settings['fallback_channel'] ?? null;

        $deliverer = $this->makeDeliverer(
            $url,
            $apiKey,
            $timeout,
            $channelName,
            is_string($fallbackChannel) ? $fallbackChannel : null,
            $queue,
        );

        return (new LoggerFactory())([
            'channel'      => $channelName,
            'level'        => $level,
            'buffer_limit' => $bufferLimit,
            'deliverer'    => $deliverer,
        ]);
    }

    /**
     * @param array<string, mixed> $queue
     */
    private function makeDeliverer(
        string $url,
        string $apiKey,
        float $timeout,
        string $channelName,
        ?string $fallbackChannel,
        array $queue,
    ): BatchDeliverer {
        $resolver = new FallbackResolver($this->app->make('log'));

        if (! empty($queue['enabled'])) {
            $safeFallback = $resolver->isSafe($fallbackChannel, $channelName) ? $fallbackChannel : null;

            return new QueuedDeliverer(
                $this->app->make(Dispatcher::class),
                [
                    'url'              => $url,
                    'api_key'          => $apiKey,
                    'timeout'          => $timeout,
                    'fallback_channel' => $safeFallback,
                ],
                $queue['connection'] ?? null,
                (string) ($queue['queue'] ?? 'openlogs'),
            );
        }

        $client = $this->app->bound(ClientInterface::class) ? $this->app->make(ClientInterface::class) : null;

        return new SyncGuzzleDeliverer(
            $url,
            $apiKey,
            $client,
            $timeout,
            $resolver->resolve($fallbackChannel, $channelName),
        );
    }
}
