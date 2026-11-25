<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Judge run history. */
final class JudgeRuns
{
    public function __construct(private Http $http)
    {
    }

    /**
     * @param array<string,mixed> $params limit, offset, kind, rubric_id, needs_review (bool)
     * @return array{runs:mixed,pagination:mixed}
     */
    public function list(array $params = []): array
    {
        if (empty($params['needs_review'])) {
            unset($params['needs_review']);
        } else {
            $params['needs_review'] = 'true';
        }
        $body = $this->http->json('GET', '/judge/evaluations', ['query' => Params::query($params)]);
        return ['runs' => $body['data'] ?? null, 'pagination' => $body['pagination'] ?? null];
    }

    public function get(string $id): mixed
    {
        return $this->http->json('GET', '/judge/evaluations/' . rawurlencode($id))['data'] ?? null;
    }

    public function delete(string $id): void
    {
        $this->http->send('DELETE', '/judge/evaluations/' . rawurlencode($id));
    }
}
