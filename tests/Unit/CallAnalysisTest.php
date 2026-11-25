<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SixtyDB\Client;
use SixtyDB\Resources\CallAnalysis;
use SixtyDB\SixtyDBException;

/** Call analysis (/deep-analytics) routes, paged batch items and the error envelope (offline). */
final class CallAnalysisTest extends TestCase
{
    use MockedClientTrait;

    private const A = '3f1c2d4e-5a6b-4c7d-8e9f-0a1b2c3d4e5f';
    private const B = '9b7e6d5c-4b3a-4291-8f0e-1d2c3b4a5968';
    private const I = 'c1d2e3f4-a5b6-4c7d-9e8f-7a6b5c4d3e2f';
    private const C = '7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d';
    private const F = '1b2c3d4e-5f6a-4b7c-8d9e-0f1a2b3c4d5e';

    public function testRoutes(): void
    {
        $a = '/deep-analytics/analysis-agents/' . self::A;
        $b = '/deep-analytics/imports/batches/' . self::B;
        $item = "$b/items/" . self::I;
        $call = '/deep-analytics/calls/' . self::C;
        $analyzer = ['name' => 'Refunds', 'instructions' => 'Find wrong refund answers', 'key_terms' => ['refund']];
        $this->assertRoutes([
            [fn (Client $c) => $c->callAnalysis->analyzers->list(), 'GET /deep-analytics/analysis-agents', null],
            [fn (Client $c) => $c->callAnalysis->analyzers->create($analyzer), 'POST /deep-analytics/analysis-agents', $analyzer],
            [fn (Client $c) => $c->callAnalysis->analyzers->get(self::A), "GET $a", null],
            [fn (Client $c) => $c->callAnalysis->analyzers->update(self::A, ['description' => null]), "PUT $a", ['description' => null]],
            [fn (Client $c) => $c->callAnalysis->analyzers->archive(self::A), "DELETE $a", null],
            [fn (Client $c) => $c->callAnalysis->dialerNumbers(), 'GET /deep-analytics/analysis-agents/dialer-numbers', null],
            [fn (Client $c) => $c->callAnalysis->sources->get(self::A), "GET $a/sources", null],
            [fn (Client $c) => $c->callAnalysis->sources->set(self::A, ['agent_ids' => ['x'], 'auto_new' => true, 'min_seconds' => 20]),
                "PUT $a/sources", ['agent_ids' => ['x'], 'auto_new' => true, 'min_seconds' => 20]],
            [fn (Client $c) => $c->callAnalysis->analyzeAgentCalls(self::A, ['c1', 'c2']), "POST $a/agent-calls", ['call_ids' => ['c1', 'c2']]],
            [fn (Client $c) => $c->callAnalysis->analyzeDialerCalls(self::A, ['k' => 'd1']), "POST $a/dialer-calls", ['call_ids' => ['d1']]],
            [fn (Client $c) => $c->callAnalysis->batches->create(self::A, ['source_kind' => 'csv_urls', 'csv_text' => "recording_url\nhttps://x.test/1.mp3"]),
                "POST $a/batches", ['source_kind' => 'csv_urls', 'csv_text' => "recording_url\nhttps://x.test/1.mp3"]],
            [fn (Client $c) => $c->callAnalysis->batches->get(self::B), "GET $b", null],
            [fn (Client $c) => $c->callAnalysis->batches->createUploadSlot(self::B, self::I, ['size_bytes' => 10, 'content_type' => 'audio/mpeg']),
                "POST $item/upload-slot", ['size_bytes' => 10, 'content_type' => 'audio/mpeg']],
            [fn (Client $c) => $c->callAnalysis->batches->confirmUpload(self::B, self::I, ['receipt_ref' => 'r1']), "POST $item/asset-receipt", ['receipt_ref' => 'r1']],
            [fn (Client $c) => $c->callAnalysis->batches->fetchRecording(self::B, self::I), "POST $item/fetch-recording", '{}'],
            [fn (Client $c) => $c->callAnalysis->batches->fetchRecordings(self::B), "POST $b/fetch-recordings", '{}'],
            [fn (Client $c) => $c->callAnalysis->batches->fetchRecordings(self::B, ['limit' => 10]), "POST $b/fetch-recordings", ['limit' => 10]],
            [fn (Client $c) => $c->callAnalysis->batches->analyze(self::A, self::B, 3), "POST $a/batches/" . self::B . '/analyze', ['confirm_count' => 3]],
            [fn (Client $c) => $c->callAnalysis->analyzeAll(self::A, 0), "POST $a/analyze", ['confirm_count' => 0]],
            [fn (Client $c) => $c->callAnalysis->recordings(self::A, ['status' => 'analyzed', 'q' => 'refund policy', 'limit' => 10]),
                "GET $a/recordings?status=analyzed&q=refund%20policy&limit=10", null],
            [fn (Client $c) => $c->callAnalysis->problems(self::A, ['from' => '2026-11-01']), "GET $a/problems?from=2026-11-01", null],
            [fn (Client $c) => $c->callAnalysis->analytics(self::A, ['days' => 7]), "GET $a/analytics?days=7", null],
            [fn (Client $c) => $c->callAnalysis->overview(['days' => 30]), 'GET /deep-analytics/analysis-agents-analytics?days=30', null],
            [fn (Client $c) => $c->callAnalysis->overview(), 'GET /deep-analytics/analysis-agents-analytics', null],
            [fn (Client $c) => $c->callAnalysis->getCall(self::C), "GET $call/analysis", null],
            [fn (Client $c) => $c->callAnalysis->agentFix->suggest(self::C), "POST $call/agent-fix/suggest", '{}'],
            [fn (Client $c) => $c->callAnalysis->agentFix->apply(self::C, ['base_hash' => 'h', 'script' => 's']), "POST $call/agent-fix/apply", ['base_hash' => 'h', 'script' => 's']],
            [fn (Client $c) => $c->callAnalysis->agentFix->undo(self::C, self::F), "POST $call/agent-fix/" . self::F . '/undo', '{}'],
        ]);
    }

    public function testBatchItemsKeepsPagination(): void
    {
        $page = ['success' => true, 'data' => [['id' => self::I, 'state' => 'draft']], 'pagination' => ['total' => 2, 'limit' => 1, 'offset' => 0]];
        $c = $this->client([$this->jsonResponse($page)]);
        $got = $c->callAnalysis->batches->items(self::B, ['state' => 'draft', 'limit' => 1, 'offset' => null]);
        $this->assertSame(['data' => $page['data'], 'pagination' => $page['pagination']], $got);
        $this->assertSame('GET /deep-analytics/imports/batches/' . self::B . '/items?state=draft&limit=1', $this->sent(0)[0]);
    }

    public function testErrorEnvelopeIsTheAgentsError(): void
    {
        $this->assertInstanceOf(CallAnalysis::class, $this->client([])->callAnalysis);
        $c = $this->client([$this->jsonResponse(['success' => false, 'code' => 'DA_API_KEY_SCOPE_MISSING', 'message' => 'needs call_analysis', 'field' => 'scope'], 403)]);
        try {
            $c->callAnalysis->analyzers->list();
            $this->fail('expected error');
        } catch (SixtyDBException $e) {
            $this->assertSame([403, 'DA_API_KEY_SCOPE_MISSING', 'scope', 'needs call_analysis'], [$e->getStatus(), $e->getErrorCode(), $e->getField(), $e->getMessage()]);
        }
    }
}
