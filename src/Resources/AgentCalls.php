<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;
use SixtyDB\Poll;

/** Agent call history, transcripts, recordings. Methods return the unwrapped `data`. */
final class AgentCalls
{
    public function __construct(private Http $http)
    {
    }

    /**
     * `from` / `to` are a DATE range (ISO 8601), not phone numbers.
     *
     * @param array<string,mixed> $params agentId, status, direction, sentiment, from, to, limit, offset
     */
    public function list(array $params = []): mixed
    {
        $query = Params::query(Params::rename($params, ['agentId' => 'agent_id']));
        return $this->http->json('GET', '/agents/calls', ['query' => $query])['data'] ?? null;
    }

    public function get(string $callId): mixed
    {
        return $this->http->json('GET', '/agents/calls/' . rawurlencode($callId))['data'] ?? null;
    }

    /**
     * Place a call that carries its own agent (rings a real phone, spends credits). 202 → the call.
     *
     * @param array<string,mixed> $body to, phone_line_id | sip{domain, username, password, caller_id},
     *        agent{script, voice_id, ...}, settings{max_duration_secs, ...call_defaults}, metadata?, variables?
     * @param array{idempotencyKey?:string} $options
     */
    public function placeInline(array $body, array $options = []): mixed
    {
        return $this->http->data('POST', '/agents/calls', ['json' => $body] + Http::idempotency($options['idempotencyKey'] ?? null));
    }

    /** `{measured, total, services, turns, phone, price_usd}`. */
    public function usage(string $callId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/calls/%s/usage', $callId));
    }

    /** Tool calls made during this call. */
    public function toolEvents(string $callId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/calls/%s/tool-events', $callId));
    }

    public function transcript(string $callId): mixed
    {
        return $this->http->json('GET', '/agents/calls/' . rawurlencode($callId) . '/transcript')['data'] ?? null;
    }

    /** WAV bytes (caller left, agent right; `$mix = true` for one channel). */
    public function recording(string $callId, bool $mix = false): string
    {
        $options = $mix ? ['query' => ['mix' => 1]] : [];
        return (string) $this->http->send('GET', '/agents/calls/' . rawurlencode($callId) . '/recording', $options)->getBody();
    }

    public function deleteRecording(string $callId): mixed
    {
        return $this->http->json('DELETE', '/agents/calls/' . rawurlencode($callId) . '/recording')['data'] ?? null;
    }

    /** Per-turn latency slices and eval results. */
    public function result(string $callId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/calls/%s/result', $callId));
    }

    /** End a WebSocket-audio call; returns `{call, status: "ending"}`. */
    public function endStream(string $callId): mixed
    {
        return $this->http->data('DELETE', Http::path('/agents/stream-calls/%s', $callId));
    }

    /** Hang up a browser (WebRTC) call. */
    public function hangUpWeb(string $callId): mixed
    {
        return $this->http->data('DELETE', Http::path('/agents/web-calls/%s', $callId));
    }

    /**
     * Poll until the call is `completed` or `failed`. 429, 5xx and network errors are retried.
     *
     * @param array{timeoutMs?:int,intervalMs?:int} $options defaults 1200000 / 2000
     */
    public function waitForCompletion(string $callId, array $options = []): mixed
    {
        return Poll::until(
            fn () => $this->get($callId),
            static fn ($call) => is_array($call) && in_array($call['status'] ?? null, ['completed', 'failed'], true),
            $options['intervalMs'] ?? 2000,
            $options['timeoutMs'] ?? 20 * 60 * 1000,
            'Timed out waiting for the call to end',
        );
    }
}
