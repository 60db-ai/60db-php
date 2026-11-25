<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Dialer webhooks. Full JSON bodies. `delete` is a soft disable (the name stays reserved). */
final class DialerWebhooks
{
    public function __construct(private Http $http)
    {
    }

    /** @return array<mixed> */
    public function list(): array
    {
        return $this->http->json('GET', '/dialer/webhooks');
    }

    /**
     * @param array<string,mixed> $params name, url, event_types[], description?, status?, timeout_ms?,
     *        rate_limit_per_minute?, max_attempts?, max_event_age_seconds?
     * @return array<mixed>
     */
    public function create(array $params): array
    {
        return $this->http->json('POST', '/dialer/webhooks', ['json' => $params]);
    }

    /** @param array<string,mixed> $params @return array<mixed> */
    public function update(string $id, array $params): array
    {
        return $this->http->json('PATCH', '/dialer/webhooks/' . rawurlencode($id), ['json' => Params::body($params)]);
    }

    /** @return array<mixed> */
    public function delete(string $id): array
    {
        return $this->http->json('DELETE', '/dialer/webhooks/' . rawurlencode($id));
    }

    /** @return array<mixed> */
    public function rotateSecret(string $id): array
    {
        return $this->http->json('POST', '/dialer/webhooks/' . rawurlencode($id) . '/rotate-secret');
    }

    /** @return array<mixed> */
    public function test(string $id): array
    {
        return $this->http->json('POST', '/dialer/webhooks/' . rawurlencode($id) . '/test');
    }

    /** @return array<mixed> */
    public function deliveries(string $id): array
    {
        return $this->http->json('GET', '/dialer/webhooks/' . rawurlencode($id) . '/deliveries');
    }
}
