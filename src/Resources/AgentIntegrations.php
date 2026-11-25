<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/**
 * Integrations (connections agents use as tools). Methods return the unwrapped `data`.
 * OAuth providers are connected in the dashboard, not through the API.
 */
final class AgentIntegrations
{
    public function __construct(private Http $http)
    {
    }

    /** Providers, connect method, credential fields, curated actions. */
    public function catalog(): mixed
    {
        return $this->http->data('GET', '/agents/integrations/catalog');
    }

    /** Connections (secrets are never returned). */
    public function list(): mixed
    {
        return $this->http->data('GET', '/agents/integrations');
    }

    /** @param array<string,mixed> $params provider, name?, fields? (key / webhook / MCP providers; tested live) */
    public function create(array $params): mixed
    {
        return $this->http->data('POST', '/agents/integrations', ['json' => $params]);
    }

    /** @param array<string,mixed> $params name?, fields? */
    public function update(string $id, array $params): mixed
    {
        return $this->http->data('PATCH', Http::path('/agents/integrations/%s', $id), ['json' => Params::body($params)]);
    }

    public function delete(string $id): mixed
    {
        return $this->http->data('DELETE', Http::path('/agents/integrations/%s', $id));
    }

    public function test(string $id): mixed
    {
        return $this->http->data('POST', Http::path('/agents/integrations/%s/test', $id));
    }

    /** New signing secret of a custom webhook connection, returned once. */
    public function rotateSecret(string $id): mixed
    {
        return $this->http->data('POST', Http::path('/agents/integrations/%s/rotate-secret', $id));
    }

    /** Tool keys this connection offers. */
    public function tools(string $id): mixed
    {
        return $this->http->data('GET', Http::path('/agents/integrations/%s/tools', $id));
    }

    /**
     * Set exactly which agents use this connection, and which tools each gets. An agent left out loses them. → `{used_by}`.
     *
     * @param list<array{agent_id:string,tools?:list<string>}> $agents
     */
    public function assignAgents(string $id, array $agents): mixed
    {
        return $this->http->data('PUT', Http::path('/agents/integrations/%s/agents', $id), ['json' => ['agents' => array_values($agents)]]);
    }

    /**
     * Run one action of the connection. → `{ok, result}`.
     *
     * @param array<string,mixed> $arguments
     */
    public function runAction(string $id, string $action, array $arguments = []): mixed
    {
        $path = Http::path('/agents/integrations/%s/actions/%s', $id, $action);
        return $this->http->data('POST', $path, ['json' => ['arguments' => Params::body($arguments)]]);
    }

    /** Ask 60db for a new integration. @param array{name:string,details?:string} $params */
    public function request(array $params): mixed
    {
        return $this->http->data('POST', '/agents/integrations/requests', ['json' => $params]);
    }

    /** Tabs of a Google Sheet (`$spreadsheetId` may be the sheet URL). */
    public function googleSheetTabs(string $id, string $spreadsheetId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/integrations/%s/google-sheets/tabs', $id), ['query' => ['spreadsheet_id' => $spreadsheetId]]);
    }

    /** Header columns of one tab. */
    public function googleSheetColumns(string $id, string $spreadsheetId, string $sheet): mixed
    {
        $query = ['spreadsheet_id' => $spreadsheetId, 'sheet' => $sheet];
        return $this->http->data('GET', Http::path('/agents/integrations/%s/google-sheets/columns', $id), ['query' => $query]);
    }
}
