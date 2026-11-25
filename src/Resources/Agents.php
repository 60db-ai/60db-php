<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/**
 * Phone voice agents. Methods return the unwrapped `data`; failures throw SixtyDBException
 * (getStatus / getErrorCode / getField).
 */
final class Agents
{
    public readonly AgentCalls $calls;
    public readonly AgentPhoneLines $phoneLines;
    public readonly AgentSettings $settings;
    public readonly AgentBuilder $builder;
    public readonly AgentCampaigns $campaigns;
    public readonly AgentDoNotCall $doNotCall;
    public readonly AgentIntegrations $integrations;
    public readonly AgentSimulations $simulations;

    public function __construct(private Http $http)
    {
        $this->calls = new AgentCalls($http);
        $this->phoneLines = new AgentPhoneLines($http);
        $this->settings = new AgentSettings($http);
        $this->builder = new AgentBuilder($http);
        $this->campaigns = new AgentCampaigns($http);
        $this->doNotCall = new AgentDoNotCall($http);
        $this->integrations = new AgentIntegrations($http);
        $this->simulations = new AgentSimulations($http);
    }

    /** @param array{limit?:int,offset?:int} $params */
    public function list(array $params = []): mixed
    {
        return $this->http->json('GET', '/agents', ['query' => Params::query($params)])['data'] ?? null;
    }

    public function get(string $agentId): mixed
    {
        return $this->http->json('GET', '/agents/' . rawurlencode($agentId))['data'] ?? null;
    }

    /** Returns the agent plus `webhook_secret` (shown once). @param array<string,mixed> $agent name, script, voice_id, ... */
    public function create(array $agent): mixed
    {
        return $this->http->json('POST', '/agents', ['json' => $agent])['data'] ?? null;
    }

    /** @param array<string,mixed> $patch */
    public function update(string $agentId, array $patch): mixed
    {
        return $this->http->json('PATCH', '/agents/' . rawurlencode($agentId), ['json' => Params::body($patch)])['data'] ?? null;
    }

    public function delete(string $agentId): mixed
    {
        return $this->http->json('DELETE', '/agents/' . rawurlencode($agentId))['data'] ?? null;
    }

    public function rotateWebhookSecret(string $agentId): mixed
    {
        return $this->http->json('POST', '/agents/' . rawurlencode($agentId) . '/rotate-webhook-secret')['data'] ?? null;
    }

    public function toolEvents(string $agentId): mixed
    {
        return $this->http->json('GET', '/agents/' . rawurlencode($agentId) . '/tool-events')['data'] ?? null;
    }

    /**
     * Place a call as this agent (rings a real phone).
     *
     * @param array<string,mixed> $params to, phoneLineId, overrides?, variables? (fills `{{key}}` in the script), metadata?
     */
    public function call(string $agentId, array $params): mixed
    {
        $body = Params::rename($params, ['phoneLineId' => 'phone_line_id']);
        return $this->http->json('POST', '/agents/' . rawurlencode($agentId) . '/calls', ['json' => $body])['data'] ?? null;
    }

    /**
     * Start a WebSocket-audio call; returns `{call, url, expires_in}`.
     *
     * @param array<string,mixed> $params overrides?, variables?, metadata?
     */
    public function streamCall(string $agentId, array $params = []): mixed
    {
        return $this->http->json('POST', '/agents/' . rawurlencode($agentId) . '/stream-calls', ['json' => Params::body($params)])['data'] ?? null;
    }

    /**
     * Start a browser (WebRTC) call; returns `{call, answer}`.
     *
     * @param array{offer:string} $params `offer` is the finished SDP offer (starts with `v=0`)
     */
    public function webCall(string $agentId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/agents/%s/web-calls', $agentId), ['json' => $params]);
    }

    /** Names usable as `call_defaults.background_sound`. */
    public function backgroundSounds(): mixed
    {
        return $this->http->data('GET', '/agents/background-sounds');
    }
}
