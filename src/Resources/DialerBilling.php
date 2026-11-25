<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

final class DialerBilling
{
    public function __construct(private Http $http)
    {
    }

    /** @return array<mixed> */
    public function subscriptions(): array
    {
        return $this->http->json('GET', '/dialer/subscriptions');
    }

    /** @param array{limit?:int} $params @return array<mixed> */
    public function usage(array $params = []): array
    {
        return $this->http->json('GET', '/dialer/usage', ['query' => Params::query($params)]);
    }
}
