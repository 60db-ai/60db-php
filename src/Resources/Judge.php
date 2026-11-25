<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Judge: classifier answers as probability distributions. Methods return the unwrapped `data`. */
final class Judge
{
    public readonly JudgeRubrics $rubrics;
    public readonly JudgeRuns $runs;

    public function __construct(private Http $http)
    {
        $this->rubrics = new JudgeRubrics($http);
        $this->runs = new JudgeRuns($http);
    }

    /** @param array<string,mixed> $params state, questions OR rubric_id, model?, save?, label? */
    public function evaluate(array $params): mixed
    {
        return $this->http->json('POST', '/judge/evaluate', ['json' => $params])['data'] ?? null;
    }

    /** @param array<string,mixed> $params text, schema, profile?, budget_ms?, save?, label? */
    public function extract(array $params): mixed
    {
        return $this->http->json('POST', '/judge/extract', ['json' => $params])['data'] ?? null;
    }

    public function models(): mixed
    {
        return $this->http->json('GET', '/judge/models')['data'] ?? null;
    }

    /** @param string $period current_month | last_30_days | all_time */
    public function usage(string $period = 'current_month'): mixed
    {
        return $this->http->json('GET', '/judge/usage', ['query' => Params::query(['period' => $period])])['data'] ?? null;
    }

    public function health(): mixed
    {
        return $this->http->json('GET', '/judge/health')['data'] ?? null;
    }
}
