<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Saved judge rubrics. Methods return the unwrapped `data`. */
final class JudgeRubrics
{
    public function __construct(private Http $http)
    {
    }

    public function list(): mixed
    {
        return $this->http->json('GET', '/judge/rubrics')['data'] ?? null;
    }

    /** @param array<string,mixed> $params name, questions, description?, model?, shared? */
    public function create(array $params): mixed
    {
        return $this->http->json('POST', '/judge/rubrics', ['json' => $params])['data'] ?? null;
    }

    public function get(string $id): mixed
    {
        return $this->http->json('GET', '/judge/rubrics/' . rawurlencode($id))['data'] ?? null;
    }

    /** @param array<string,mixed> $params */
    public function update(string $id, array $params): mixed
    {
        return $this->http->json('PATCH', '/judge/rubrics/' . rawurlencode($id), ['json' => Params::body($params)])['data'] ?? null;
    }

    public function delete(string $id): void
    {
        $this->http->send('DELETE', '/judge/rubrics/' . rawurlencode($id));
    }
}
