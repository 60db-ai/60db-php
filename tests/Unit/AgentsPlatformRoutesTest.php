<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SixtyDB\Client;

/** Verb, path, query and body of every new agents-platform method (offline, Guzzle mock handler). */
final class AgentsPlatformRoutesTest extends TestCase
{
    use MockedClientTrait;

    public function testAgentCallAndPhoneLineAdditions(): void
    {
        $inline = ['to' => '+15550001', 'phone_line_id' => 'l1', 'agent' => ['script' => 'Hi', 'voice_id' => 'v1'],
            'settings' => ['max_duration_secs' => 300], 'variables' => ['name' => 'Asha']];
        $this->assertRoutes([
            [fn (Client $c) => $c->agents->webCall('a 1', ['offer' => "v=0\r\n"]), 'POST /agents/a%201/web-calls', ['offer' => "v=0\r\n"]],
            [fn (Client $c) => $c->agents->backgroundSounds(), 'GET /agents/background-sounds', null],
            [fn (Client $c) => $c->agents->settings->get(), 'GET /agents/settings', null],
            [fn (Client $c) => $c->agents->settings->update(['event_webhook_url' => null, 'tool_webhook_url' => 'https://x.test/t']),
                'PUT /agents/settings', ['event_webhook_url' => null, 'tool_webhook_url' => 'https://x.test/t']],
            [fn (Client $c) => $c->agents->settings->rotateWebhookSecret(), 'POST /agents/settings/rotate-webhook-secret', null],
            [fn (Client $c) => $c->agents->call('a1', ['to' => '+1', 'phoneLineId' => 'l1', 'variables' => ['name' => 'Asha']]),
                'POST /agents/a1/calls', ['to' => '+1', 'phone_line_id' => 'l1', 'variables' => ['name' => 'Asha']]],
            [fn (Client $c) => $c->agents->streamCall('a1', ['variables' => ['k' => 'v']]), 'POST /agents/a1/stream-calls', ['variables' => ['k' => 'v']]],
            [fn (Client $c) => $c->agents->calls->list(['agentId' => 'a1', 'sentiment' => 'happy', 'to' => '2026-11-30', 'limit' => 5]),
                'GET /agents/calls?agent_id=a1&sentiment=happy&to=2026-11-30&limit=5', null],
            [fn (Client $c) => $c->agents->calls->placeInline($inline), 'POST /agents/calls', $inline],
            [fn (Client $c) => $c->agents->calls->usage('c1'), 'GET /agents/calls/c1/usage', null],
            [fn (Client $c) => $c->agents->calls->toolEvents('c1'), 'GET /agents/calls/c1/tool-events', null],
            [fn (Client $c) => $c->agents->calls->result('c/1'), 'GET /agents/calls/c%2F1/result', null],
            [fn (Client $c) => $c->agents->calls->endStream('c1'), 'DELETE /agents/stream-calls/c1', null],
            [fn (Client $c) => $c->agents->calls->hangUpWeb('c1'), 'DELETE /agents/web-calls/c1', null],
            [fn (Client $c) => $c->agents->phoneLines->setup('l1'), 'GET /agents/phone-lines/l1/setup', null],
            [fn (Client $c) => $c->agents->phoneLines->check('l1', ['called_number' => '+15550001', 'source_ip' => '203.0.113.9']),
                'POST /agents/phone-lines/l1/check', ['called_number' => '+15550001', 'source_ip' => '203.0.113.9']],
            [fn (Client $c) => $c->agents->phoneLines->check('l1', ['calledNumber' => '+1', 'callerNumber' => '+2']),
                'POST /agents/phone-lines/l1/check', ['called_number' => '+1', 'caller_number' => '+2']],
            [fn (Client $c) => $c->agents->phoneLines->create(['kind' => 'byo', 'name' => 'n', 'domain' => 'sip.x', 'callerId' => '+1',
                'numbers' => [['number' => '+1', 'agent_id' => 'a']], 'allowedAddresses' => ['203.0.113.9'], 'normalize_numbers' => true, 'bridgeTransfers' => false]),
                'POST /agents/phone-lines', ['kind' => 'byo', 'name' => 'n', 'domain' => 'sip.x', 'caller_id' => '+1',
                    'numbers' => [['number' => '+1', 'agent_id' => 'a']], 'allowed_addresses' => ['203.0.113.9'], 'normalize_numbers' => true, 'bridge_transfers' => false]],
            [fn (Client $c) => $c->agents->phoneLines->update('l1', ['defaultCountryCode' => null, 'allowed_numbers' => ['+1']]),
                'PATCH /agents/phone-lines/l1', ['default_country_code' => null, 'allowed_numbers' => ['+1']]],
            [fn (Client $c) => $c->agents->builder->getDraft('d1'), 'GET /agents/builder/drafts/d1', null],
        ]);
    }

    public function testCampaignsRecipientsAndDoNotCall(): void
    {
        $create = ['name' => 'Diwali', 'agent_id' => 'a1', 'phone_line_id' => 'l1', 'timezone' => 'Asia/Kolkata', 'csv_text' => "phone\n+919800000001",
            'calling_windows' => [['days' => [1, 2, 3], 'start' => '09:00', 'end' => '18:00']], 'max_consecutive_failures' => null];
        $this->assertRoutes([
            [fn (Client $c) => $c->agents->campaigns->summary(), 'GET /agents/campaigns/summary', null],
            [fn (Client $c) => $c->agents->campaigns->previewRecipients(['csv_text' => "phone\n+1", 'agent_id' => 'a1']),
                'POST /agents/campaigns/recipients/preview', ['csv_text' => "phone\n+1", 'agent_id' => 'a1']],
            [fn (Client $c) => $c->agents->campaigns->create($create), 'POST /agents/campaigns', $create],
            [fn (Client $c) => $c->agents->campaigns->get('c 1'), 'GET /agents/campaigns/c%201', null],
            [fn (Client $c) => $c->agents->campaigns->analytics('c1', ['from' => '2026-11-01', 'to' => null]), 'GET /agents/campaigns/c1/analytics?from=2026-11-01', null],
            [fn (Client $c) => $c->agents->campaigns->pause('c1'), 'POST /agents/campaigns/c1/pause', null],
            [fn (Client $c) => $c->agents->campaigns->resume('c1'), 'POST /agents/campaigns/c1/resume', null],
            [fn (Client $c) => $c->agents->campaigns->cancel('c1'), 'POST /agents/campaigns/c1/cancel', null],
            [fn (Client $c) => $c->agents->campaigns->recipients->add('c1', ['recipients' => [['phone' => '+1', 'variables' => ['n' => 'A']]]]),
                'POST /agents/campaigns/c1/recipients', ['recipients' => [['phone' => '+1', 'variables' => ['n' => 'A']]]]],
            [fn (Client $c) => $c->agents->campaigns->recipients->preview('c1', ['csv_text' => 'phone']), 'POST /agents/campaigns/c1/recipients/preview', ['csv_text' => 'phone']],
            [fn (Client $c) => $c->agents->campaigns->recipients->calls('c1', 'r1'), 'GET /agents/campaigns/c1/recipients/r1/calls', null],
            [fn (Client $c) => $c->agents->campaigns->recipients->cancelCallback('c1', 'r1'), 'POST /agents/campaigns/c1/recipients/r1/callback/cancel', null],
            [fn (Client $c) => $c->agents->campaigns->recipients->callbackNow('c1', 'r1'), 'POST /agents/campaigns/c1/recipients/r1/callback/call-now', null],
            [fn (Client $c) => $c->agents->doNotCall->add(['phone' => '+919800000001', 'reason' => 'asked']), 'POST /agents/do-not-call', ['phone' => '+919800000001', 'reason' => 'asked']],
            [fn (Client $c) => $c->agents->doNotCall->remove('e1'), 'DELETE /agents/do-not-call/e1', null],
            [fn (Client $c) => $c->agents->doNotCall->removeByPhone('+919800000001'), 'DELETE /agents/do-not-call?phone=%2B919800000001', null],
        ]);
    }

    public function testIntegrations(): void
    {
        $this->assertRoutes([
            [fn (Client $c) => $c->agents->integrations->catalog(), 'GET /agents/integrations/catalog', null],
            [fn (Client $c) => $c->agents->integrations->list(), 'GET /agents/integrations', null],
            [fn (Client $c) => $c->agents->integrations->create(['provider' => 'hubspot', 'fields' => ['api_key' => 'k']]),
                'POST /agents/integrations', ['provider' => 'hubspot', 'fields' => ['api_key' => 'k']]],
            [fn (Client $c) => $c->agents->integrations->update('i1', ['name' => 'CRM']), 'PATCH /agents/integrations/i1', ['name' => 'CRM']],
            [fn (Client $c) => $c->agents->integrations->delete('i1'), 'DELETE /agents/integrations/i1', null],
            [fn (Client $c) => $c->agents->integrations->test('i1'), 'POST /agents/integrations/i1/test', null],
            [fn (Client $c) => $c->agents->integrations->rotateSecret('i1'), 'POST /agents/integrations/i1/rotate-secret', null],
            [fn (Client $c) => $c->agents->integrations->tools('i1'), 'GET /agents/integrations/i1/tools', null],
            [fn (Client $c) => $c->agents->integrations->assignAgents('i1', [['agent_id' => 'a1', 'tools' => ['crm.lookup']], ['agent_id' => 'a2']]),
                'PUT /agents/integrations/i1/agents', ['agents' => [['agent_id' => 'a1', 'tools' => ['crm.lookup']], ['agent_id' => 'a2']]]],
            [fn (Client $c) => $c->agents->integrations->runAction('i1', 'send_email'), 'POST /agents/integrations/i1/actions/send_email', '{"arguments":{}}'],
            [fn (Client $c) => $c->agents->integrations->runAction('i1', 'lookup', ['email' => 'a@b.c']),
                'POST /agents/integrations/i1/actions/lookup', ['arguments' => ['email' => 'a@b.c']]],
            [fn (Client $c) => $c->agents->integrations->request(['name' => 'Zoho', 'details' => 'CRM']), 'POST /agents/integrations/requests', ['name' => 'Zoho', 'details' => 'CRM']],
            [fn (Client $c) => $c->agents->integrations->googleSheetTabs('i1', 'https://docs.google.com/spreadsheets/d/abc'),
                'GET /agents/integrations/i1/google-sheets/tabs?spreadsheet_id=https%3A%2F%2Fdocs.google.com%2Fspreadsheets%2Fd%2Fabc', null],
            [fn (Client $c) => $c->agents->integrations->googleSheetColumns('i1', 'abc', 'Sheet 1'),
                'GET /agents/integrations/i1/google-sheets/columns?spreadsheet_id=abc&sheet=Sheet%201', null],
        ]);
    }

    public function testSimulationsEvalsMetricsGolden(): void
    {
        $fix = ['base_hash' => 'h', 'script' => 's'];
        $this->assertRoutes([
            [fn (Client $c) => $c->agents->simulations->start('a1', ['mode' => 'generate', 'count' => 9, 'max_credits' => 5]),
                'POST /agents/a1/simulations', ['mode' => 'generate', 'count' => 9, 'max_credits' => 5]],
            [fn (Client $c) => $c->agents->simulations->estimate('a1', ['scenarios' => 9, 'repeats' => 2]), 'POST /agents/a1/simulations/estimate', ['scenarios' => 9, 'repeats' => 2]],
            [fn (Client $c) => $c->agents->simulations->estimate('a1'), 'POST /agents/a1/simulations/estimate', '{}'],
            [fn (Client $c) => $c->agents->simulations->get('r1'), 'GET /agents/simulations/r1', null],
            [fn (Client $c) => $c->agents->simulations->pause('r1'), 'POST /agents/simulations/r1/pause', null],
            [fn (Client $c) => $c->agents->simulations->resume('r1'), 'POST /agents/simulations/r1/resume', null],
            [fn (Client $c) => $c->agents->simulations->cancel('r1'), 'POST /agents/simulations/r1/cancel', null],
            [fn (Client $c) => $c->agents->simulations->createDraft('r1'), 'POST /agents/simulations/r1/draft', null],
            [fn (Client $c) => $c->agents->simulations->updateDraft('d1', 'Be brief.'), 'PUT /agents/simulation-drafts/d1', ['script' => 'Be brief.']],
            [fn (Client $c) => $c->agents->simulations->rerun('r1', ['agent' => 'current', 'repeats' => 2]), 'POST /agents/simulations/r1/rerun', ['agent' => 'current', 'repeats' => 2]],
            [fn (Client $c) => $c->agents->simulations->rerunCall('r1', 'c1', ['draft_id' => 'd1']), 'POST /agents/simulations/r1/calls/c1/rerun', ['draft_id' => 'd1']],
            [fn (Client $c) => $c->agents->simulations->suggestFix('r1'), 'POST /agents/simulations/r1/fix', null],
            [fn (Client $c) => $c->agents->simulations->applyFix('r1', $fix), 'POST /agents/simulations/r1/fix/apply', $fix],
            [fn (Client $c) => $c->agents->simulations->undoFix('r1', $fix), 'POST /agents/simulations/r1/fix/undo', $fix],
            [fn (Client $c) => $c->agents->simulations->evals('a1'), 'GET /agents/a1/evals', null],
            [fn (Client $c) => $c->agents->simulations->metrics('a1', ['source' => 'production', 'agent_version' => 3, 'from' => 1700000000]),
                'GET /agents/a1/metrics?source=production&agent_version=3&from=1700000000', null],
            [fn (Client $c) => $c->agents->simulations->compareMetrics('a1', ['source' => 'simulation', 'version_a' => 1, 'version_b' => 2]),
                'GET /agents/a1/metrics/compare?source=simulation&version_a=1&version_b=2', null],
            [fn (Client $c) => $c->agents->simulations->runGolden('a1', 'greets caller'), 'POST /agents/a1/evals/greets%20caller/golden', null],
            [fn (Client $c) => $c->agents->simulations->getGoldenRun('greets caller', 'g1'), 'GET /agents/evals/greets%20caller/golden/runs/g1', null],
        ]);
    }
}
