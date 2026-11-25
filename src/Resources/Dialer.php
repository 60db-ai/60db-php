<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;

/** Dialer: numbers, call logs, recordings, billing, webhooks. Full JSON bodies. */
final class Dialer
{
    public readonly DialerNumbers $numbers;
    public readonly DialerCalls $calls;
    public readonly DialerRecordings $recordings;
    public readonly DialerBilling $billing;
    public readonly DialerWebhooks $webhooks;

    public function __construct(private Http $http)
    {
        $this->numbers = new DialerNumbers($http);
        $this->calls = new DialerCalls($http);
        $this->recordings = new DialerRecordings($http);
        $this->billing = new DialerBilling($http);
        $this->webhooks = new DialerWebhooks($http);
    }

    /** @return array<mixed> */
    public function status(): array
    {
        return $this->http->json('GET', '/dialer/status');
    }
}
