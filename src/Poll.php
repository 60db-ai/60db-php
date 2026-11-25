<?php

declare(strict_types=1);

namespace SixtyDB;

/** Internal polling loop shared by the wait helpers. */
final class Poll
{
    /**
     * Call `$fetch` until `$done($value)` is true and return that value. 429, 5xx and network errors are retried;
     * any other error is thrown. Throws SixtyDBException (code TIMEOUT) when the next poll would pass the deadline.
     */
    public static function until(callable $fetch, callable $done, int $intervalMs, int $timeoutMs, string $timeoutMessage): mixed
    {
        $deadline = hrtime(true) / 1e6 + $timeoutMs;
        while (true) {
            $value = null;
            try {
                $value = $fetch();
            } catch (SixtyDBException $e) {
                $s = $e->getStatus();
                if ($s !== 0 && $s !== 429 && $s < 500) {
                    throw $e;
                }
            }
            if ($done($value)) {
                return $value;
            }
            if (hrtime(true) / 1e6 + $intervalMs > $deadline) {
                throw new SixtyDBException($timeoutMessage, 0, 'TIMEOUT');
            }
            usleep($intervalMs * 1000);
        }
    }
}
