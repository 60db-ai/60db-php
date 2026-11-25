<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Call analysis analyzers (analysis agents). Methods return the unwrapped `data`. */
final class CallAnalysisAnalyzers
{
    public function __construct(private Http $http)
    {
    }

    public function list(): mixed
    {
        return $this->http->data('GET', '/deep-analytics/analysis-agents');
    }

    /** 201. @param array<string,mixed> $params name, instructions, description?, knowledge_collection_id?, key_terms?, language? */
    public function create(array $params): mixed
    {
        return $this->http->data('POST', '/deep-analytics/analysis-agents', ['json' => $params]);
    }

    public function get(string $analyzerId): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/analysis-agents/%s', $analyzerId));
    }

    /** Sent as PUT (the API's verb); any create field, at least one. @param array<string,mixed> $patch */
    public function update(string $analyzerId, array $patch): mixed
    {
        return $this->http->data('PUT', Http::path('/deep-analytics/analysis-agents/%s', $analyzerId), ['json' => Params::body($patch)]);
    }

    public function archive(string $analyzerId): mixed
    {
        return $this->http->data('DELETE', Http::path('/deep-analytics/analysis-agents/%s', $analyzerId));
    }
}
