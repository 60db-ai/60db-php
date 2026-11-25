<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** The workspace's do-not-call numbers (campaigns skip them). `list` returns `['data', 'pagination']`. */
final class AgentDoNotCall
{
    public function __construct(private Http $http)
    {
    }

    /**
     * @param array<string,mixed> $params q, limit, offset
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function list(array $params = []): array
    {
        return $this->http->page('GET', '/agents/do-not-call', ['query' => Params::query($params)]);
    }

    /** @param array{phone:string,reason?:string} $params */
    public function add(array $params): mixed
    {
        return $this->http->data('POST', '/agents/do-not-call', ['json' => $params]);
    }

    public function remove(string $entryId): mixed
    {
        return $this->http->data('DELETE', Http::path('/agents/do-not-call/%s', $entryId));
    }

    public function removeByPhone(string $phone): mixed
    {
        return $this->http->data('DELETE', '/agents/do-not-call', ['query' => ['phone' => $phone]]);
    }
}
