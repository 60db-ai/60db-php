<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\LineReader;
use SixtyDB\SixtyDBException;

final class Chat
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Non-streaming completion (OpenAI shape: choices[0].message.content).
     * Pass `messages` (chat mode) or `text` (text-correction mode).
     *
     * @param array<string,mixed> $params
     * @return array<mixed>
     */
    public function completion(array $params): array
    {
        return $this->http->json('POST', '/v1/chat/completions', ['json' => $this->body($params, false), 'timeout' => 300]);
    }

    /**
     * Streaming completion; yields decoded SSE chunks (incl. the leading `chat_id` chunk).
     *
     * @param array<string,mixed> $params
     * @return \Generator<int,array<mixed>>
     */
    public function stream(array $params): \Generator
    {
        $res = $this->http->send('POST', '/v1/chat/completions', [
            'json' => $this->body($params, true), 'stream' => true, 'timeout' => 300,
        ]);
        foreach (LineReader::lines($res->getBody()) as $line) {
            if (!str_starts_with($line, 'data:')) {
                continue;
            }
            $payload = trim(substr($line, 5));
            if ($payload === '[DONE]') {
                return;
            }
            $chunk = json_decode($payload, true);
            if (!is_array($chunk)) {
                continue;
            }
            if (($chunk['type'] ?? null) === 'error' || !empty($chunk['error'])) {
                throw new SixtyDBException(self::errorMessage($chunk), $res->getStatusCode(), self::errorCode($chunk), null, $chunk);
            }
            yield $chunk;
        }
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    private function body(array $params, bool $stream): array
    {
        if (!isset($params['messages']) && !isset($params['text'])) {
            throw new \InvalidArgumentException('Provide either "messages" or "text"');
        }
        if (isset($params['app_context'])) {
            $params['appContext'] = $params['app_context'];
            unset($params['app_context']);
        }
        return array_merge(['model' => 'vyas'], Http::clean($params), ['stream' => $stream]);
    }

    /** @param array<mixed> $chunk */
    private static function errorCode(array $chunk): ?string
    {
        $err = $chunk['error'] ?? null;
        if (is_string($err)) {
            $err = json_decode($err, true);
        }
        $code = $chunk['code'] ?? (is_array($err) ? ($err['code'] ?? $err['error']['code'] ?? null) : null);
        return is_scalar($code) ? (string) $code : null;
    }

    /** @param array<mixed> $chunk */
    private static function errorMessage(array $chunk): string
    {
        $err = $chunk['error'] ?? null;
        if (is_string($err)) {
            $inner = json_decode($err, true);
            $msg = is_array($inner) ? ($inner['error']['message'] ?? $inner['message'] ?? null) : null;
            if (is_string($msg)) {
                return $msg;
            }
        } elseif (is_array($err) && is_string($err['message'] ?? null)) {
            return $err['message'];
        }
        return is_string($chunk['message'] ?? null) ? $chunk['message'] : (is_string($err) ? $err : 'Chat stream error');
    }
}
