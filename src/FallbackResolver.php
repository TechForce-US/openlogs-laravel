<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel;

use Illuminate\Log\LogManager;
use Monolog\Handler\GroupHandler;
use Monolog\Handler\HandlerInterface;

/**
 * Resolves a Laravel log channel into a Monolog fallback handler for the core
 * deliverer, and guards against a fallback that would point back at the OpenLogs
 * channel (which would create an infinite logging loop).
 */
final class FallbackResolver
{
    private bool $warned = false;

    public function __construct(private readonly LogManager $logs)
    {
    }

    /**
     * Returns true if the fallback channel is safe to use (not the OpenLogs
     * channel itself). Emits a one-time warning when it is not.
     */
    public function isSafe(?string $fallbackChannel, string $openLogsChannel): bool
    {
        if ($fallbackChannel === null || $fallbackChannel === '') {
            return false;
        }

        if ($fallbackChannel === $openLogsChannel || $fallbackChannel === 'openlogs') {
            if (! $this->warned) {
                $this->warned = true;
                error_log(sprintf(
                    '[openlogs] fallback_channel "%s" points back at the openlogs channel; fallback disabled.',
                    $fallbackChannel,
                ));
            }

            return false;
        }

        return true;
    }

    /**
     * Build a single Monolog handler that forwards to the given channel's
     * handlers, or null when the channel is unsafe/absent.
     */
    public function resolve(?string $fallbackChannel, string $openLogsChannel): ?HandlerInterface
    {
        if (! $this->isSafe($fallbackChannel, $openLogsChannel)) {
            return null;
        }

        $handlers = $this->logs->channel($fallbackChannel)->getLogger()->getHandlers();

        return new GroupHandler($handlers);
    }
}
