<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Dialer recordings. Full JSON bodies. */
final class DialerRecordings
{
    public function __construct(private Http $http)
    {
    }

    /** @param array<string,mixed> $params direction, status, e164, limit, offset @return array<mixed> */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/dialer/recordings', ['query' => Params::query($params)]);
    }

    /** @return array<mixed> */
    public function playbackUrl(string $id): array
    {
        return $this->http->json('POST', '/dialer/recordings/' . rawurlencode($id) . '/playback-url');
    }

    /** @return array<mixed> */
    public function transcript(string $id): array
    {
        return $this->http->json('POST', '/dialer/recordings/' . rawurlencode($id) . '/transcript');
    }

    /** @return array<mixed> */
    public function delete(string $id): array
    {
        return $this->http->json('DELETE', '/dialer/recordings/' . rawurlencode($id));
    }

    /** @return array<mixed> */
    public function setEnabled(bool $enabled): array
    {
        return $this->http->json('PUT', '/dialer/recording', ['json' => ['enabled' => $enabled]]);
    }
}
