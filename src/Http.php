<?php

declare(strict_types=1);

namespace SixtyDB;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/** Internal transport: auth header, error mapping, JSON decoding. */
final class Http
{
    public function __construct(
        private ClientInterface $guzzle,
        private string $apiKey,
        private string $baseUrl,
        private float $timeout,
    ) {
    }

    /**
     * Send a request; throws SixtyDBException on transport failure or status >= 400.
     *
     * @param array<string,mixed> $options Guzzle options (json, multipart, query, timeout, stream, headers).
     */
    public function send(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = ['Authorization' => 'Bearer ' . $this->apiKey] + ($options['headers'] ?? []);
        $options['http_errors'] = false;
        $options['timeout'] ??= $this->timeout;
        try {
            $res = $this->guzzle->request($method, $this->baseUrl . $path, $options);
        } catch (GuzzleException $e) {
            throw new SixtyDBException($e->getMessage(), 0, null, null, null, $e);
        }
        if ($res->getStatusCode() >= 400) {
            throw SixtyDBException::fromResponse($res->getStatusCode(), (string) $res->getBody());
        }
        return $res;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>
     */
    public function json(string $method, string $path, array $options = []): array
    {
        return self::decode($this->send($method, $path, $options));
    }

    /**
     * The `data` of a `{success, data}` envelope.
     *
     * @param array<string,mixed> $options
     */
    public function data(string $method, string $path, array $options = []): mixed
    {
        return $this->json($method, $path, $options)['data'] ?? null;
    }

    /**
     * A paged-list envelope `{success, data: [...], pagination}`, keeping both parts.
     *
     * @param array<string,mixed> $options
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function page(string $method, string $path, array $options = []): array
    {
        $body = $this->json($method, $path, $options);
        return ['data' => $body['data'] ?? [], 'pagination' => $body['pagination'] ?? null];
    }

    /**
     * The raw response body as a string (CSV and other non-JSON answers).
     *
     * @param array<string,mixed> $options
     */
    public function text(string $method, string $path, array $options = []): string
    {
        return (string) $this->send($method, $path, $options)->getBody();
    }

    /**
     * Guzzle options carrying an `Idempotency-Key` header, or none when no key was given.
     *
     * @return array<string,mixed>
     */
    public static function idempotency(?string $key): array
    {
        return $key === null || $key === '' ? [] : ['headers' => ['Idempotency-Key' => $key]];
    }

    /** Fill each `%s` of the path with a URL-escaped id, e.g. path('/agents/%s/calls', $id). */
    public static function path(string $format, string ...$ids): string
    {
        return sprintf($format, ...array_map('rawurlencode', $ids));
    }

    /** @return array<mixed> */
    public static function decode(ResponseInterface $res): array
    {
        $raw = (string) $res->getBody();
        if (trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new SixtyDBException('Invalid JSON response', $res->getStatusCode(), null, null, $raw);
        }
        return $data;
    }

    /** Remove null values so optional params are omitted from the JSON body. */
    public static function clean(array $params): array
    {
        return array_filter($params, static fn ($v) => $v !== null);
    }
}
