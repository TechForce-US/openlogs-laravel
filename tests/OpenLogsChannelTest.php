<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use TechForce\OpenLogs\Laravel\SendLogBatch;

final class OpenLogsChannelTest extends TestCase
{
    public function test_channel_builds_a_logger(): void
    {
        $logger = Log::channel('openlogs')->getLogger();

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('openlogs', $logger->getName());
    }

    public function test_sync_delivery_posts_a_batch_on_flush(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(201)]));
        $stack->push(Middleware::history($history));
        $this->app->instance(ClientInterface::class, new Client(['handler' => $stack]));

        $channel = Log::channel('openlogs');
        $channel->info('first', ['a' => 1]);
        $channel->error('second');

        self::assertCount(0, $history, 'buffered until flush');

        $channel->getLogger()->close(); // flush the BufferHandler

        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('https://logs.example.com/api/ingest/batch', (string) $request->getUri());
        self::assertSame('secret', $request->getHeaderLine('X-API-Key'));
        $body = (string) $request->getBody();
        self::assertStringContainsString('"message":"first"', $body);
        self::assertStringContainsString('"message":"second"', $body);
    }

    public function test_queued_delivery_dispatches_job_on_dedicated_queue(): void
    {
        config()->set('openlogs.queue.enabled', true);
        config()->set('openlogs.queue.queue', 'openlogs');
        Bus::fake();

        $channel = Log::channel('openlogs');
        $channel->error('boom', ['x' => 9]);
        $channel->getLogger()->close();

        Bus::assertDispatched(SendLogBatch::class, function (SendLogBatch $job) {
            return $job->queue === 'openlogs'
                && count($job->entries) === 1
                && $job->entries[0]['message'] === 'boom'
                && $job->config['api_key'] === 'secret';
        });
    }

    public function test_queued_payload_contains_only_normalized_entries(): void
    {
        config()->set('openlogs.queue.enabled', true);
        Bus::fake();

        $channel = Log::channel('openlogs');
        $channel->warning('w');
        $channel->getLogger()->close();

        Bus::assertDispatched(SendLogBatch::class, function (SendLogBatch $job) {
            foreach ($job->entries as $entry) {
                self::assertIsArray($entry);
                self::assertArrayHasKey('level_name', $entry);
            }

            return true;
        });
    }
}
