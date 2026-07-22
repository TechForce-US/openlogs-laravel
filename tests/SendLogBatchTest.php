<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use RuntimeException;
use TechForce\OpenLogs\Laravel\SendLogBatch;

final class SendLogBatchTest extends TestCase
{
    private function entry(string $message = 'hi', string $level = 'ERROR'): array
    {
        return [
            'message'    => $message,
            'level_name' => $level,
            'level'      => 400,
            'channel'    => 'openlogs',
            'datetime'   => '2026-07-21T00:00:00.000000+00:00',
            'context'    => ['k' => 'v'],
            'extra'      => [],
        ];
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'url'              => 'https://logs.example.com',
            'api_key'          => 'secret',
            'timeout'          => 5.0,
            'fallback_channel' => 'capture',
        ], $overrides);
    }

    public function test_handle_posts_batch_on_success(): void
    {
        $mock = new MockHandler([new Response(201)]);
        $this->app->instance(ClientInterface::class, new Client(['handler' => HandlerStack::create($mock)]));

        (new SendLogBatch([$this->entry()], $this->config()))->handle();

        self::assertSame(0, $mock->count(), 'the queued request was consumed');
    }

    public function test_handle_throws_on_non_201_so_queue_can_retry(): void
    {
        $this->app->instance(ClientInterface::class, new Client([
            'handler' => HandlerStack::create(new MockHandler([new Response(500)])),
        ]));

        $this->expectException(RuntimeException::class);
        (new SendLogBatch([$this->entry()], $this->config()))->handle();
    }

    public function test_failed_replays_entries_to_fallback_channel(): void
    {
        $job = new SendLogBatch(
            [$this->entry('down', 'ERROR'), $this->entry('again', 'WARNING')],
            $this->config(),
        );

        $job->failed(new RuntimeException('exhausted'));

        $handler = $this->captureHandler();
        self::assertTrue($handler->hasRecordThatContains('down', \Monolog\Level::Error));
        self::assertTrue($handler->hasRecordThatContains('again', \Monolog\Level::Warning));
    }

    public function test_failed_without_fallback_channel_is_a_noop(): void
    {
        $job = new SendLogBatch([$this->entry()], $this->config(['fallback_channel' => null]));

        $this->expectNotToPerformAssertions();
        $job->failed(new RuntimeException('x'));
    }
}
