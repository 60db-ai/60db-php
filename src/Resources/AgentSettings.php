<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;

/** Workspace-wide agent settings (default webhooks and limits). Methods return the unwrapped `data`. */
final class AgentSettings
{
    public function __construct(private Http $http)
    {
    }

    /** `{event_webhook_url, tool_webhook_url, limits{max_concurrent_calls, max_call_secs, ...}}`. */
    public function get(): mixed
    {
        return $this->http->data('GET', '/agents/settings');
    }

    /**
     * Returns the settings plus `webhook_secret`.
     *
     * @param array{event_webhook_url?:?string,tool_webhook_url?:?string} $params https URL, or null to clear; at least one
     */
    public function update(array $params): mixed
    {
        return $this->http->data('PUT', '/agents/settings', ['json' => $params]);
    }

    /** Returns the settings plus the new `webhook_secret`. */
    public function rotateWebhookSecret(): mixed
    {
        return $this->http->data('POST', '/agents/settings/rotate-webhook-secret');
    }
}
