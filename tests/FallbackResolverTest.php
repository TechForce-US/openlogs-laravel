<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel\Tests;

use Monolog\Handler\HandlerInterface;
use TechForce\OpenLogs\Laravel\FallbackResolver;

final class FallbackResolverTest extends TestCase
{
    private function resolver(): FallbackResolver
    {
        return new FallbackResolver($this->app->make('log'));
    }

    public function test_resolves_a_channel_to_a_handler(): void
    {
        $handler = $this->resolver()->resolve('single', 'openlogs');

        self::assertInstanceOf(HandlerInterface::class, $handler);
    }

    public function test_fallback_pointing_at_openlogs_is_unsafe(): void
    {
        $resolver = $this->resolver();

        self::assertFalse($resolver->isSafe('openlogs', 'openlogs'));
        self::assertNull($resolver->resolve('openlogs', 'openlogs'));
    }

    public function test_fallback_equal_to_own_channel_name_is_unsafe(): void
    {
        self::assertFalse($this->resolver()->isSafe('mychannel', 'mychannel'));
    }

    public function test_null_or_empty_fallback_is_unsafe(): void
    {
        $resolver = $this->resolver();

        self::assertFalse($resolver->isSafe(null, 'openlogs'));
        self::assertFalse($resolver->isSafe('', 'openlogs'));
    }

    public function test_distinct_channel_is_safe(): void
    {
        self::assertTrue($this->resolver()->isSafe('single', 'openlogs'));
    }
}
