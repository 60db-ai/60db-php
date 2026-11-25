<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Fix the 60db AI agent behind an analyzed call. Methods return the unwrapped `data`. */
final class CallAnalysisAgentFix
{
    public function __construct(private Http $http)
    {
    }

    /** Suggest a script change (saves nothing). */
    public function suggest(string $callId): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/calls/%s/agent-fix/suggest', $callId), ['json' => Params::body([])]);
    }

    /** @param array{base_hash:string,script:string} $params */
    public function apply(string $callId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/calls/%s/agent-fix/apply', $callId), ['json' => $params]);
    }

    public function undo(string $callId, string $fixId): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/calls/%s/agent-fix/%s/undo', $callId, $fixId), ['json' => Params::body([])]);
    }
}
