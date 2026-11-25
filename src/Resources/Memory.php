<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Upload;

/** Smart Memory / RAG. Every method returns the full JSON body. */
final class Memory
{
    private const LLM_TIMEOUT = 120;

    public function __construct(private Http $http)
    {
    }

    /**
     * @param array<string,mixed> $params title, type, collection, infer, session_id, metadata
     * @return array<mixed>
     */
    public function ingest(string $text, array $params = []): array
    {
        return $this->http->json('POST', '/memory/ingest', ['json' => ['text' => $text] + $params, 'timeout' => self::LLM_TIMEOUT]);
    }

    /**
     * @param array<string,mixed> $params mode, max_results, alpha, recency_bias, collection, type
     * @return array<mixed>
     */
    public function search(string $query, array $params = []): array
    {
        return $this->http->json('POST', '/memory/search', ['json' => ['query' => $query] + $params, 'timeout' => self::LLM_TIMEOUT]);
    }

    /**
     * Build prompt-ready context (`data.prompt_ready`).
     *
     * @param array<string,mixed> $params session_id, top_k, max_context_length, include_timeline, collection
     * @return array<mixed>
     */
    public function context(string $query, array $params = []): array
    {
        return $this->http->json('POST', '/memory/context', ['json' => ['query' => $query] + $params, 'timeout' => self::LLM_TIMEOUT]);
    }

    /**
     * Upload a document (PDF, DOCX, TXT...) and ingest its chunks.
     *
     * @param string|resource $file path, stream resource, or raw bytes
     * @param array<string,mixed> $fields collection, type, title, ...
     * @return array<mixed>
     */
    public function extractDocument($file, ?string $filename = null, array $fields = []): array
    {
        [$bytes, $pathName] = Upload::read($file);
        $parts = [['name' => 'file', 'contents' => $bytes, 'filename' => $filename ?? $pathName ?? 'document']];
        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $parts[] = ['name' => (string) $key, 'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
            }
        }
        return $this->http->json('POST', '/memory/documents/extract', ['multipart' => $parts, 'timeout' => 300]);
    }

    /** @return array<mixed> */
    public function usage(): array
    {
        return $this->http->json('GET', '/memory/usage');
    }
}
