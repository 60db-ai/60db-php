<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/**
 * A campaign's recipients. Methods return the unwrapped `data`; `list` returns `['data', 'pagination']`;
 * `template` and `export` return the CSV text.
 */
final class AgentCampaignRecipients
{
    public function __construct(private Http $http)
    {
    }

    /**
     * @param array<string,mixed> $params status (pending|calling|completed|no_answer|failed|skipped|cancelled|callback),
     *        q (phone search), sentiment (last call's label), limit, offset
     * @return array{data:array<mixed>,pagination:mixed}
     */
    public function list(string $campaignId, array $params = []): array
    {
        return $this->http->page('GET', Http::path('/agents/campaigns/%s/recipients', $campaignId), ['query' => Params::query($params)]);
    }

    /**
     * Add recipients (dialing starts). 201 (200 on an idempotent replay) → `{..., campaign}`.
     *
     * @param array<string,mixed> $body csv_text OR recipients[{phone, variables?}]
     * @param array{idempotencyKey?:string} $options
     */
    public function add(string $campaignId, array $body, array $options = []): mixed
    {
        $path = Http::path('/agents/campaigns/%s/recipients', $campaignId);
        return $this->http->data('POST', $path, ['json' => $body] + Http::idempotency($options['idempotencyKey'] ?? null));
    }

    /** @param array<string,mixed> $body same as `add`; stores nothing */
    public function preview(string $campaignId, array $body): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/recipients/preview', $campaignId), ['json' => $body]);
    }

    /** CSV with this campaign's columns, to fill in and add. */
    public function template(string $campaignId): string
    {
        return $this->http->text('GET', Http::path('/agents/campaigns/%s/recipients/template', $campaignId));
    }

    /**
     * CSV of every matching recipient (UTF-8 with a BOM).
     *
     * @param array<string,mixed> $params status, q, sentiment
     */
    public function export(string $campaignId, array $params = []): string
    {
        return $this->http->text('GET', Http::path('/agents/campaigns/%s/recipients/export', $campaignId), ['query' => Params::query($params)]);
    }

    /** Every call to this recipient, newest first. */
    public function calls(string $campaignId, string $recipientId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/campaigns/%s/recipients/%s/calls', $campaignId, $recipientId));
    }

    /** Drop the scheduled callback (409 CALLBACK_NOT_SCHEDULED when there is none). */
    public function cancelCallback(string $campaignId, string $recipientId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/recipients/%s/callback/cancel', $campaignId, $recipientId));
    }

    /** Make the scheduled callback due now. */
    public function callbackNow(string $campaignId, string $recipientId): mixed
    {
        return $this->http->data('POST', Http::path('/agents/campaigns/%s/recipients/%s/callback/call-now', $campaignId, $recipientId));
    }
}
