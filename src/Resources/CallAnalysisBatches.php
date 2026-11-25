<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Recordings in via CSV batches. Methods return the unwrapped `data`; `items` returns `['data', 'pagination']`. */
final class CallAnalysisBatches
{
    public function __construct(private Http $http)
    {
    }

    /** 201. @param array<string,mixed> $params source_kind (direct_upload|csv_urls), csv_text, name?, mapping?, default_agent_id? */
    public function create(string $analyzerId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/analysis-agents/%s/batches', $analyzerId), ['json' => $params]);
    }

    public function get(string $batchId): mixed
    {
        return $this->http->data('GET', Http::path('/deep-analytics/imports/batches/%s', $batchId));
    }

    /**
     * @param array<string,mixed> $params state?, limit?, offset?
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function items(string $batchId, array $params = []): array
    {
        return $this->http->page('GET', Http::path('/deep-analytics/imports/batches/%s/items', $batchId), ['query' => Params::query($params)]);
    }

    /**
     * Presigned form POST for one row's file → `{upload_id, expires_at, max_size_bytes, content_type, upload{method, url, fields}}`.
     *
     * @param array{size_bytes:int,content_type:string} $params
     */
    public function createUploadSlot(string $batchId, string $itemId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/imports/batches/%s/items/%s/upload-slot', $batchId, $itemId), ['json' => $params]);
    }

    /** @param array{receipt_ref:string} $params */
    public function confirmUpload(string $batchId, string $itemId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/imports/batches/%s/items/%s/asset-receipt', $batchId, $itemId), ['json' => $params]);
    }

    /** Fetch one row's recording URL. */
    public function fetchRecording(string $batchId, string $itemId): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/imports/batches/%s/items/%s/fetch-recording', $batchId, $itemId), ['json' => Params::body([])]);
    }

    /** @param array{limit?:int} $params */
    public function fetchRecordings(string $batchId, array $params = []): mixed
    {
        return $this->http->data('POST', Http::path('/deep-analytics/imports/batches/%s/fetch-recordings', $batchId), ['json' => Params::body(Http::clean($params))]);
    }

    /** Analyze a batch; `$confirmCount` = its number of valid rows (`valid` from `create`). */
    public function analyze(string $analyzerId, string $batchId, int $confirmCount): mixed
    {
        $path = Http::path('/deep-analytics/analysis-agents/%s/batches/%s/analyze', $analyzerId, $batchId);
        return $this->http->data('POST', $path, ['json' => ['confirm_count' => $confirmCount]]);
    }
}
