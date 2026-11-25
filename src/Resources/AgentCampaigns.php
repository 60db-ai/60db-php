<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/**
 * Outbound calling campaigns (they ring real phones and spend credits). Methods return the unwrapped `data`;
 * `list` returns `['data' => [...], 'pagination' => [...]]`.
 */
final class AgentCampaigns
{
    public readonly AgentCampaignRecipients $recipients;

    public function __construct(private Http $http)
    {
        $this->recipients = new AgentCampaignRecipients($http);
    }

    /**
     * @param array<string,mixed> $params status (draft|scheduled|running|paused|completed|cancelled), q, limit, offset
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function list(array $params = []): array
    {
        return $this->http->page('GET', '/agents/campaigns', ['query' => Params::query($params)]);
    }

    /** Campaigns running, recipients waiting, on a call. */
    public function summary(): mixed
    {
        return $this->http->data('GET', '/agents/campaigns/summary');
    }

    /**
     * Check a CSV before creating a campaign (valid, invalid, duplicates, do-not-call).
     *
     * @param array<string,mixed> $params csv_text, phone_line_id?, agent_id?
     */
    public function previewRecipients(array $params): mixed
    {
        return $this->http->data('POST', '/agents/campaigns/recipients/preview', ['json' => $params]);
    }

    /**
     * Create a campaign. 201 (200 on an idempotent replay) → the campaign.
     *
     * @param array<string,mixed> $body name, agent_id, phone_line_id, timezone, csv_text; start?, calling_windows?,
     *        concurrency_limit?, max_attempts?, retry_delay_secs?, max_consecutive_failures?, variable_mapping?, variable_defaults?
     * @param array{idempotencyKey?:string} $options
     */
    public function create(array $body, array $options = []): mixed
    {
        return $this->http->data('POST', '/agents/campaigns', ['json' => $body] + Http::idempotency($options['idempotencyKey'] ?? null));
    }

    /** The campaign with counts, effective concurrency and the workspace limit. */
    public function get(string $campaignId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/campaigns/%s', $campaignId));
    }

    /** @param array{from?:string,to?:string} $params days as YYYY-MM-DD in the campaign timezone */
    public function analytics(string $campaignId, array $params = []): mixed
    {
        return $this->http->data('GET', Http::path('/agents/campaigns/%s/analytics', $campaignId), ['query' => Params::query($params)]);
    }

    public function pause(string $campaignId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/pause', $campaignId));
    }

    public function resume(string $campaignId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/resume', $campaignId));
    }

    /** No new dials; calls already live finish. */
    public function cancel(string $campaignId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/cancel', $campaignId));
    }
}
