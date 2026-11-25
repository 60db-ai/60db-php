<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;
use SixtyDB\Poll;

/**
 * Reliability: simulations, evals, metrics, golden runs. Runs, reruns, fix suggestions and golden runs spend credits.
 * Methods return the unwrapped `data`; `list` returns `['data', 'pagination']`.
 */
final class AgentSimulations
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Runs of an agent, newest first.
     *
     * @param array{limit?:int,offset?:int} $params
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function list(string $agentId, array $params = []): array
    {
        return $this->http->page('GET', Http::path('/agents/%s/simulations', $agentId), ['query' => Params::query($params)]);
    }

    /**
     * Start a run. 201 → the run.
     *
     * @param array<string,mixed> $body mode (generate|custom); generate: count, sample_rate; custom: scenario{};
     *        repeats, priority (manual|ci), max_concurrency, max_calls, max_minutes, max_credits, estimated_credits_per_call, baseline_run_id
     */
    public function start(string $agentId, array $body): mixed
    {
        return $this->http->data('POST', Http::path('/agents/%s/simulations', $agentId), ['json' => $body]);
    }

    /** Free dry run. @param array<string,mixed> $body scenarios, repeats, priority, max_calls, max_credits, estimated_credits_per_call */
    public function estimate(string $agentId, array $body = []): mixed
    {
        return $this->http->data('POST', Http::path('/agents/%s/simulations/estimate', $agentId), ['json' => Params::body($body)]);
    }

    /** Progress and report; `state` is running, paused, queued, cancelled, finished or stopped_budget. */
    public function get(string $runId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/simulations/%s', $runId));
    }

    /**
     * Poll until the run is `finished`, `cancelled` or `stopped_budget`. 429, 5xx and network errors are retried.
     *
     * @param array{timeoutMs?:int,intervalMs?:int} $options defaults 1800000 / 4000
     */
    public function waitForRun(string $runId, array $options = []): mixed
    {
        return Poll::until(
            fn () => $this->get($runId),
            static fn ($run) => is_array($run) && in_array($run['state'] ?? null, ['finished', 'cancelled', 'stopped_budget'], true),
            $options['intervalMs'] ?? 4000,
            $options['timeoutMs'] ?? 30 * 60 * 1000,
            'Timed out waiting for the simulation run',
        );
    }

    public function pause(string $runId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/pause', $runId));
    }

    /** Needs a Pro or Business plan. */
    public function resume(string $runId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/resume', $runId));
    }

    public function cancel(string $runId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/cancel', $runId));
    }

    /** Unpublished draft of the agent from a run. 201. */
    public function createDraft(string $runId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/draft', $runId));
    }

    /** Replace the draft's script (up to 32,000 characters). */
    public function updateDraft(string $draftId, string $script): mixed
    {
        return $this->http->data('PUT', Http::path('/agents/simulation-drafts/%s', $draftId), ['json' => ['script' => $script]]);
    }

    /** @param array<string,mixed> $body draft_id OR agent: "current"; repeats, priority, max_concurrency, max_calls, max_minutes, max_credits, ... */
    public function rerun(string $runId, array $body): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/rerun', $runId), ['json' => $body]);
    }

    /** @param array<string,mixed> $body draft_id (required) and the same run controls as `rerun` */
    public function rerunCall(string $runId, string $callId, array $body): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/calls/%s/rerun', $runId, $callId), ['json' => $body]);
    }

    /** One paid model request; saves nothing. → `{base_hash, before, after, changes, problems, settings_problems}`. */
    public function suggestFix(string $runId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/fix', $runId));
    }

    /** 409 when the agent changed since the suggestion. @param array{base_hash:string,script:string} $params */
    public function applyFix(string $runId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/fix/apply', $runId), ['json' => $params]);
    }

    /** @param array{base_hash:string,script:string} $params */
    public function undoFix(string $runId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/agents/simulations/%s/fix/undo', $runId), ['json' => $params]);
    }

    /** Built-in and custom evals with golden-set status. */
    public function evals(string $agentId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/%s/evals', $agentId));
    }

    /** @param array<string,mixed> $params source (production|simulation|test), agent_version, from?, to? (unix seconds) */
    public function metrics(string $agentId, array $params): mixed
    {
        return $this->http->data('GET', Http::path('/agents/%s/metrics', $agentId), ['query' => Params::query($params)]);
    }

    /** @param array<string,mixed> $params source, version_a, version_b, from?, to? */
    public function compareMetrics(string $agentId, array $params): mixed
    {
        return $this->http->data('GET', Http::path('/agents/%s/metrics/compare', $agentId), ['query' => Params::query($params)]);
    }

    /** Run an eval's golden set (one paid request per fixture). */
    public function runGolden(string $agentId, string $evalName): mixed
    {
        return $this->http->data('POST', Http::path('/agents/%s/evals/%s/golden', $agentId, $evalName));
    }

    public function getGoldenRun(string $evalName, string $runId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/evals/%s/golden/runs/%s', $evalName, $runId));
    }
}
