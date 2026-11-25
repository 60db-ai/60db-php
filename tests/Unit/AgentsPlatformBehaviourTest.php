<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SixtyDB\Client;
use SixtyDB\SixtyDBException;

/** Paged envelopes, CSV bodies, Idempotency-Key, builder multipart, errors and the waiters (offline). */
final class AgentsPlatformBehaviourTest extends TestCase
{
    use MockedClientTrait;

    public function testPagedListsKeepDataAndPagination(): void
    {
        $page = ['success' => true, 'data' => [['id' => 'x']], 'pagination' => ['total' => 11, 'limit' => 10, 'offset' => 0]];
        $want = ['data' => [['id' => 'x']], 'pagination' => ['total' => 11, 'limit' => 10, 'offset' => 0]];
        $cases = [
            [fn (Client $c) => $c->agents->campaigns->list(['status' => 'running', 'q' => 'diwali', 'limit' => 10, 'offset' => 0]),
                'GET /agents/campaigns?status=running&q=diwali&limit=10&offset=0'],
            [fn (Client $c) => $c->agents->campaigns->recipients->list('c1', ['status' => 'callback', 'sentiment' => 'angry', 'q' => null]),
                'GET /agents/campaigns/c1/recipients?status=callback&sentiment=angry'],
            [fn (Client $c) => $c->agents->doNotCall->list(['q' => '+91']), 'GET /agents/do-not-call?q=%2B91'],
            [fn (Client $c) => $c->agents->simulations->list('a1', ['limit' => 5]), 'GET /agents/a1/simulations?limit=5'],
            [fn (Client $c) => $c->callAnalysis->batches->items('b1'), 'GET /deep-analytics/imports/batches/b1/items'],
        ];
        $c = $this->client(array_fill(0, count($cases), $this->jsonResponse($page)));
        foreach ($cases as $i => [$call, $route]) {
            $this->assertSame($want, $call($c), $route);
            $this->assertSame($route, $this->sent($i)[0]);
        }
    }

    public function testPagedListWithoutPaginationStillHasBothKeys(): void
    {
        $c = $this->client([$this->jsonResponse(['success' => true, 'data' => []])]);
        $this->assertSame(['data' => [], 'pagination' => null], $c->agents->doNotCall->list());
    }

    public function testRecipientCsvEndpointsReturnText(): void
    {
        $csv = "\u{FEFF}phone,name\r\n+919800000001,Asha\r\n";
        $c = $this->client([
            new Response(200, ['Content-Type' => 'text/csv; charset=utf-8'], $csv),
            new Response(200, ['Content-Type' => 'text/csv; charset=utf-8'], $csv),
            $this->jsonResponse(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Campaign not found'], 404),
        ]);
        $this->assertSame($csv, $c->agents->campaigns->recipients->template('c1'));
        $this->assertSame('GET /agents/campaigns/c1/recipients/template', $this->sent(0)[0]);
        $this->assertSame($csv, $c->agents->campaigns->recipients->export('c1', ['status' => 'completed', 'q' => '98', 'sentiment' => 'happy']));
        $this->assertSame('GET /agents/campaigns/c1/recipients/export?status=completed&q=98&sentiment=happy', $this->sent(1)[0]);
        try {
            $c->agents->campaigns->recipients->export('c1');
            $this->fail('expected error');
        } catch (SixtyDBException $e) {
            $this->assertSame([404, 'NOT_FOUND', 'Campaign not found'], [$e->getStatus(), $e->getErrorCode(), $e->getMessage()]);
        }
    }

    public function testIdempotencyKeyIsSentOnlyWhenGiven(): void
    {
        $ok = $this->jsonResponse(['success' => true, 'data' => ['id' => 'x']]);
        $c = $this->client(array_fill(0, 6, $ok));
        $c->agents->campaigns->create(['name' => 'n'], ['idempotencyKey' => 'camp-1']);
        $c->agents->campaigns->create(['name' => 'n']);
        $c->agents->campaigns->recipients->add('c1', ['csv_text' => 'phone'], ['idempotencyKey' => 'rec-1']);
        $c->agents->campaigns->recipients->add('c1', ['csv_text' => 'phone']);
        $c->agents->calls->placeInline(['to' => '+1'], ['idempotencyKey' => 'call-1']);
        $c->agents->calls->placeInline(['to' => '+1']);
        foreach ([0 => 'camp-1', 2 => 'rec-1', 4 => 'call-1'] as $i => $key) {
            $r = $this->sent($i)[2];
            $this->assertSame($key, $r->getHeaderLine('Idempotency-Key'));
            $this->assertSame('Bearer test-key', $r->getHeaderLine('Authorization'));
            $this->assertFalse($this->sent($i + 1)[2]->hasHeader('Idempotency-Key'));
        }
        $this->assertSame(['POST /agents/campaigns', 'POST /agents/campaigns/c1/recipients', 'POST /agents/calls'],
            [$this->sent(0)[0], $this->sent(2)[0], $this->sent(4)[0]]);
    }

    public function testBuilderCreateDraftIsMultipartWithFilesField(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'sdk');
        $path = $base . '.md';
        file_put_contents($path, "# FAQ\nOpen 9-5.");
        unlink($base);
        $c = $this->client([$this->jsonResponse(['success' => true, 'data' => ['id' => 'd1', 'status' => 'queued']], 202)]);
        try {
            $got = $c->agents->builder->createDraft(['description' => 'Front desk for a clinic', 'direction' => 'inbound', 'language' => 'en',
                'website_url' => 'https://clinic.test', 'files' => [$path, ['file' => 'Refunds within 7 days.', 'filename' => 'policy.txt']]]);
        } finally {
            unlink($path);
        }
        $this->assertSame(['id' => 'd1', 'status' => 'queued'], $got);
        [$route, $body, $r] = $this->sent(0);
        $this->assertSame('POST /agents/builder/drafts', $route);
        $this->assertStringStartsWith('multipart/form-data; boundary=', $r->getHeaderLine('Content-Type'));
        $this->assertMatchesRegularExpression('/name="description"\r\n(?:[^\r\n]+\r\n)*\r\nFront desk for a clinic\r\n/', $body);
        $this->assertMatchesRegularExpression('/name="direction"\r\n(?:[^\r\n]+\r\n)*\r\ninbound\r\n/', $body);
        $this->assertMatchesRegularExpression('/name="website_url"\r\n(?:[^\r\n]+\r\n)*\r\nhttps:\/\/clinic.test\r\n/', $body);
        $this->assertStringContainsString('name="files"; filename="' . basename($path) . '"', $body);
        $this->assertMatchesRegularExpression('/name="files"; filename="policy.txt"\r\n(?:[^\r\n]+\r\n)*\r\nRefunds within 7 days\.\r\n/', $body);
        $this->assertSame(2, substr_count($body, 'name="files"'));
    }

    public function testBuilderRejectsBytesWithoutFilename(): void
    {
        $c = $this->client([]);
        $this->expectException(\InvalidArgumentException::class);
        $c->agents->builder->createDraft(['description' => 'd', 'direction' => 'outbound', 'files' => ['raw bytes, no name']]);
    }

    public function testErrorEnvelopeMapsToAgentsError(): void
    {
        $c = $this->client([
            $this->jsonResponse(['success' => false, 'message' => 'timezone is not valid', 'code' => 'VALIDATION_ERROR', 'field' => 'timezone'], 400),
            $this->jsonResponse(['success' => false, 'message' => 'No callback is scheduled', 'code' => 'CALLBACK_NOT_SCHEDULED'], 409),
            new Response(502, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>'),
        ]);
        $calls = [
            fn () => $c->agents->campaigns->create(['name' => 'n']),
            fn () => $c->agents->campaigns->recipients->cancelCallback('c1', 'r1'),
            fn () => $c->agents->integrations->list(),
        ];
        $want = [[400, 'VALIDATION_ERROR', 'timezone', 'timezone is not valid'], [409, 'CALLBACK_NOT_SCHEDULED', null, 'No callback is scheduled'], [502, null, null, 'HTTP 502']];
        foreach ($calls as $i => $call) {
            try {
                $call();
                $this->fail("expected error $i");
            } catch (SixtyDBException $e) {
                $this->assertSame($want[$i], [$e->getStatus(), $e->getErrorCode(), $e->getField(), $e->getMessage()]);
            }
        }
    }

    public function testWaitForDraftStopsOnReadyAndFailedWithoutThrowing(): void
    {
        $draft = fn (string $status, array $extra = []) => $this->jsonResponse(['success' => true, 'data' => ['id' => 'd1', 'status' => $status] + $extra]);
        $c = $this->client([$draft('queued'), $this->jsonResponse(['message' => 'slow down'], 429), $this->jsonResponse([], 503), $draft('writing'), $draft('ready', ['draft' => ['agent' => ['name' => 'A']]])]);
        $got = $c->agents->builder->waitForDraft('d1', ['intervalMs' => 1]);
        $this->assertSame(['name' => 'A'], $got['draft']['agent']);
        $this->assertCount(5, $this->history);
        $this->assertSame('GET /agents/builder/drafts/d1', $this->sent(4)[0]);

        $c = $this->client([$draft('extracting'), $draft('failed', ['error' => ['code' => 'NO_TEXT', 'message' => 'nothing to read']])]);
        $this->assertSame('NO_TEXT', $c->agents->builder->waitForDraft('d1', ['intervalMs' => 1])['error']['code']);
    }

    public function testWaitForDraftThrowsOnClientErrorAndTimesOut(): void
    {
        $c = $this->client([$this->jsonResponse(['code' => 'NOT_FOUND'], 404)]);
        try {
            $c->agents->builder->waitForDraft('d1', ['intervalMs' => 1]);
            $this->fail('expected error');
        } catch (SixtyDBException $e) {
            $this->assertSame(404, $e->getStatus());
        }
        $c = $this->client(array_fill(0, 50, $this->jsonResponse(['data' => ['status' => 'reading']])));
        try {
            $c->agents->builder->waitForDraft('d1', ['intervalMs' => 1, 'timeoutMs' => 5]);
            $this->fail('expected timeout');
        } catch (SixtyDBException $e) {
            $this->assertSame('TIMEOUT', $e->getErrorCode());
        }
    }

    public function testWaitForRunStopsOnEachTerminalState(): void
    {
        $run = fn (string $state) => $this->jsonResponse(['success' => true, 'data' => ['run_id' => 'r1', 'state' => $state]]);
        foreach (['finished', 'cancelled', 'stopped_budget'] as $terminal) {
            $c = $this->client([$run('queued'), $this->jsonResponse([], 500), $run('running'), $run('paused'), $run($terminal)]);
            $this->assertSame($terminal, $c->agents->simulations->waitForRun('r1', ['intervalMs' => 1])['state']);
            $this->assertCount(5, $this->history);
            $this->assertSame('GET /agents/simulations/r1', $this->sent(4)[0]);
        }
        $c = $this->client(array_fill(0, 50, $run('running')));
        try {
            $c->agents->simulations->waitForRun('r1', ['intervalMs' => 1, 'timeoutMs' => 5]);
            $this->fail('expected timeout');
        } catch (SixtyDBException $e) {
            $this->assertSame('TIMEOUT', $e->getErrorCode());
        }
    }
}
