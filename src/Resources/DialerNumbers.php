<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Dialer phone numbers. Full JSON bodies. */
final class DialerNumbers
{
    public function __construct(private Http $http)
    {
    }

    /** @return array<mixed> */
    public function search(): array
    {
        return $this->http->json('GET', '/dialer/pool');
    }

    /**
     * Buy a number (charges $6.00 now, then $6.00/month; needs approved dialer KYC).
     *
     * @param array<string,mixed> $params number, assign_for, outbound_trunk_id, inbound_trunk_id, channel_limit
     * @return array<mixed>
     */
    public function buy(array $params = []): array
    {
        return $this->http->json('POST', '/dialer/pool/allocate', ['json' => Params::body($params)]);
    }

    /** @return array<mixed> */
    public function list(): array
    {
        return $this->http->json('GET', '/dialer/numbers');
    }

    /** @return array<mixed> */
    public function release(string $e164): array
    {
        return $this->http->json('DELETE', '/dialer/numbers/' . rawurlencode($e164));
    }

    /** @return array<mixed> */
    public function setCallerId(string $e164): array
    {
        return $this->http->json('PUT', '/dialer/caller-id', ['json' => ['caller_id' => $e164]]);
    }
}
