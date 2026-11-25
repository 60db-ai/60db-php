<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Dialer call logs. Full JSON bodies. */
final class DialerCalls
{
    public function __construct(private Http $http)
    {
    }

    /** @param array{limit?:int,offset?:int} $params @return array<mixed> */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/dialer/logs', ['query' => Params::query($params)]);
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/dialer/calls/' . rawurlencode($id));
    }

    /** @return array<mixed> */
    public function live(): array
    {
        return $this->http->json('GET', '/dialer/live');
    }

    /** @return array<mixed> */
    public function lineStatus(): array
    {
        return $this->http->json('GET', '/dialer/calls/line-status');
    }

    /**
     * Reserve a call id before a SIP client sends its INVITE (does NOT dial).
     *
     * @param array<string,mixed> $params to, from, sipCallId, direction?
     * @return array<mixed>
     */
    public function reserve(array $params): array
    {
        return $this->http->json('POST', '/dialer/calls', ['json' => Params::rename($params, ['sipCallId' => 'sip_call_id'])]);
    }
}
