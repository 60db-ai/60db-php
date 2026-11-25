<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/**
 * Call analysis (base path /deep-analytics). Needs a user API key with the `call_analysis` permission
 * (403 DA_API_KEY_SCOPE_MISSING otherwise). An "analyzer" is an analysis agent; ids are UUIDs.
 * Methods return the unwrapped `data`; failures throw SixtyDBException.
 */
final class CallAnalysis
{
    public readonly CallAnalysisAnalyzers $analyzers;
    public readonly CallAnalysisSources $sources;
    public readonly CallAnalysisBatches $batches;
    public readonly CallAnalysisAgentFix $agentFix;

    public function __construct(private Http $http)
    {
        $this->analyzers = new CallAnalysisAnalyzers($http);
        $this->sources = new CallAnalysisSources($http);
        $this->batches = new CallAnalysisBatches($http);
        $this->agentFix = new CallAnalysisAgentFix($http);
    }

    /** Dialer numbers that can feed an analyzer. */
    public function dialerNumbers(): mixed
    {
        return $this->http->data('GET', '/deep-analytics/analysis-agents/dialer-numbers');
    }

    /** Queue 60db AI agent calls for analysis. 202. @param list<string> $callIds */
    public function analyzeAgentCalls(string $analyzerId, array $callIds): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/analysis-agents/%s/agent-calls', $analyzerId), ['json' => ['call_ids' => array_values($callIds)]]);
    }

    /** Queue dialer calls for analysis. 202. @param list<string> $callIds */
    public function analyzeDialerCalls(string $analyzerId, array $callIds): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/analysis-agents/%s/dialer-calls', $analyzerId), ['json' => ['call_ids' => array_values($callIds)]]);
    }

    /** Analyze every ready recording; `$confirmCount` = the number of recordings in status `ready`. */
    public function analyzeAll(string $analyzerId, int $confirmCount): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/analysis-agents/%s/analyze', $analyzerId), ['json' => ['confirm_count' => $confirmCount]]);
    }

    /** @param array<string,mixed> $params status?, category?, severity?, q?, limit?, offset? */
    public function recordings(string $analyzerId, array $params = []): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/analysis-agents/%s/recordings', $analyzerId), ['query' => Params::query($params)]);
    }

    /** @param array{from?:string,to?:string} $params */
    public function problems(string $analyzerId, array $params = []): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/analysis-agents/%s/problems', $analyzerId), ['query' => Params::query($params)]);
    }

    /** @param array{days?:int} $params */
    public function analytics(string $analyzerId, array $params = []): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/analysis-agents/%s/analytics', $analyzerId), ['query' => Params::query($params)]);
    }

    /** Analytics across all analyzers. @param array{days?:int} $params */
    public function overview(array $params = []): mixed
    {
        return $this->http->data('GET', '/deep-analytics/analysis-agents-analytics', ['query' => Params::query($params)]);
    }

    /** One call's analysis. */
    public function getCall(string $callId): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/calls/%s/analysis', $callId));
    }
}
