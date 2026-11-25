<?php

declare(strict_types=1);

namespace SixtyDB;

/**
 * Verify 60db webhooks: header `X-60db-Signature: v1=<hex HMAC-SHA256(secret, "{ts}.{body}")>`
 * with `X-60db-Timestamp: <unix seconds>`.
 */
final class WebhookSignature
{
    public static function compute(string $rawBody, string|int $timestamp, string $secret): string
    {
        return 'v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    public static function verify(
        string $rawBody,
        ?string $signature,
        string|int|null $timestamp,
        string $secret,
        int $toleranceSec = 300,
        ?int $now = null,
    ): bool {
        if ($secret === '' || $signature === null || $signature === '' || $timestamp === null
            || preg_match('/\A[0-9]{1,12}\z/', (string) $timestamp) !== 1) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $toleranceSec) {
            return false;
        }
        return hash_equals(self::compute($rawBody, $timestamp, $secret), $signature);
    }
}
