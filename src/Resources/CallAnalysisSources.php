<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;

/** Which calls feed an analyzer. Methods return the unwrapped `data`. */
final class CallAnalysisSources
{
    public function __construct(private Http $http)
    {
    }

    public function get(string $analyzerId): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/analysis-agents/%s/sources', $analyzerId));
    }

    /** @param array<string,mixed> $params agent_ids, dialer_number_ids?, auto_new?, min_seconds?, backfill_days?, self_heal_mode? */
    public function set(string $analyzerId, array $params): mixed
    {
        return $this->http->data('PUT', Http::path('/deep-analytics/analysis-agents/%s/sources', $analyzerId), ['json' => $params]);
    }
}
