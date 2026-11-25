<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;

/** Agent phone lines. Methods return the unwrapped `data`. */
final class AgentPhoneLines
{
    private const MAP = [
        'agentId' => 'agent_id', 'callerId' => 'caller_id', 'inboundNumber' => 'inbound_number',
        'allowedAddresses' => 'allowed_addresses', 'allowedNumbers' => 'allowed_numbers', 'normalizeNumbers' => 'normalize_numbers',
        'defaultCountryCode' => 'default_country_code', 'bridgeTransfers' => 'bridge_transfers',
    ];
    private const CHECK_MAP = ['calledNumber' => 'called_number', 'sourceIp' => 'source_ip', 'callerNumber' => 'caller_number'];

    public function __construct(private Http $http)
    {
    }

    public function list(): mixed
    {
        return $this->http->json('GET', '/agents/phone-lines')['data'] ?? null;
    }

    public function availableNumbers(): mixed
    {
        return $this->http->json('GET', '/agents/phone-lines/available-numbers')['data'] ?? null;
    }

    public function get(string $lineId): mixed
    {
        return $this->http->json('GET', '/agents/phone-lines/' . rawurlencode($lineId))['data'] ?? null;
    }

    /**
     * kind "sixtydb": number, name?, agentId?, inbound?
     * kind "byo": name, domain, callerId, username?, password? (optional for an IP-trust carrier), inboundNumber?, agentId?,
     * numbers?[{number, agent_id}], allowedAddresses?, allowedNumbers?, normalizeNumbers?, defaultCountryCode?, bridgeTransfers?
     *
     * @param array<string,mixed> $params camelCase keys are mapped to snake_case; snake_case keys pass through
     */
    public function create(array $params): mixed
    {
        return $this->http->json('POST', '/agents/phone-lines', ['json' => Params::rename($params, self::MAP)])['data'] ?? null;
    }

    /**
     * @param array<string,mixed> $params name, agentId (null unassigns), inbound, domain, username, password, callerId,
     *        inboundNumber, numbers, allowedAddresses, allowedNumbers, normalizeNumbers, defaultCountryCode, bridgeTransfers
     */
    public function update(string $lineId, array $params): mixed
    {
        return $this->http->json('PATCH', '/agents/phone-lines/' . rawurlencode($lineId), ['json' => Params::body(Params::rename($params, self::MAP))])['data'] ?? null;
    }

    public function delete(string $lineId): mixed
    {
        return $this->http->json('DELETE', '/agents/phone-lines/' . rawurlencode($lineId))['data'] ?? null;
    }

    /** What to give the carrier (SIP addresses, numbers, IPs). */
    public function setup(string $lineId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/phone-lines/%s/setup', $lineId));
    }

    /**
     * How an incoming call would be answered.
     *
     * @param array<string,mixed> $params called_number (required), source_ip?, caller_number? (camelCase accepted)
     */
    public function check(string $lineId, array $params): mixed
    {
        return $this->http->data('POST', Http::path('/agents/phone-lines/%s/check', $lineId), ['json' => Params::rename($params, self::CHECK_MAP)]);
    }
}
