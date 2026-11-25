<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SixtyDB\SixtyDBException;

final class Phase2ResourcesTest extends TestCase
{
    use MockedClientTrait;

    /** @return array{0:string,1:string,2:array<mixed>} method, path+query, decoded json body */
    private function last(int $i = 0): array
    {
        $r = $this->history[$i]['request'];
        $q = $r->getUri()->getQuery();
        return [$r->getMethod(), $r->getUri()->getPath() . ($q !== '' ? "?$q" : ''), json_decode((string) $r->getBody(), true) ?? []];
    }

    public function testJudgeUnwrapsDataAndRunsList(): void
    {
        $c = $this->client([
            $this->jsonResponse(['data' => ['ok' => 1]]),
            $this->jsonResponse(['data' => [['id' => 'r1']], 'pagination' => ['total' => 1]]),
            $this->jsonResponse(['data' => ['usage' => 2]]),
            new Response(204),
        ]);
        $this->assertSame(['ok' => 1], $c->judge->evaluate(['state' => 'hi', 'rubric_id' => 'x']));
        $runs = $c->judge->runs->list(['needs_review' => true, 'kind' => 'evaluate', 'limit' => 5]);
        $this->assertSame(['runs' => [['id' => 'r1']], 'pagination' => ['total' => 1]], $runs);
        $c->judge->usage('all_time');
        $c->judge->rubrics->delete('a/b');
        $this->assertSame(['POST', '/judge/evaluate'], array_slice($this->last(0), 0, 2));
        $this->assertSame('/judge/evaluations?needs_review=true&kind=evaluate&limit=5', $this->last(1)[1]);
        $this->assertSame('/judge/usage?period=all_time', $this->last(2)[1]);
        $this->assertSame(['DELETE', '/judge/rubrics/a%2Fb'], array_slice($this->last(3), 0, 2));
    }

    public function testJudgeRunsListOmitsNeedsReviewWhenFalse(): void
    {
        $c = $this->client([$this->jsonResponse(['data' => []])]);
        $c->judge->runs->list(['needs_review' => false]);
        $this->assertSame('/judge/evaluations', $this->last()[1]);
    }

    public function testJudgeRubricCrudPaths(): void
    {
        $ok = $this->jsonResponse(['data' => ['id' => 'q']]);
        $c = $this->client([$ok, $ok, $ok, $ok, $ok, new Response(204), $ok, $ok]);
        $c->judge->rubrics->list();
        $c->judge->rubrics->create(['name' => 'n', 'questions' => ['a' => ['type' => 'noul']]]);
        $c->judge->rubrics->get('q');
        $c->judge->rubrics->update('q', ['name' => 'm']);
        $c->judge->extract(['text' => 't', 'schema' => []]);
        $c->judge->runs->delete('r');
        $c->judge->models();
        $c->judge->health();
        $got = array_map(fn ($i) => implode(' ', array_slice($this->last($i), 0, 2)), range(0, 7));
        $this->assertSame(['GET /judge/rubrics', 'POST /judge/rubrics', 'GET /judge/rubrics/q', 'PATCH /judge/rubrics/q',
            'POST /judge/extract', 'DELETE /judge/evaluations/r', 'GET /judge/models', 'GET /judge/health'], $got);
    }

    public function testMusicCreateJsonVsMultipart(): void
    {
        $ok = $this->jsonResponse(['success' => true]);
        $c = $this->client([$ok, $ok]);
        $c->music->create(['prompt' => 'jazz', 'target_duration' => 30]);
        $this->assertSame(['prompt' => 'jazz', 'target_duration' => 30], $this->last(0)[2]);
        $c->music->create(['prompt' => 'jazz', 'instrumental' => true, 'lyrics_file' => "la la\n"]);
        $body = (string) $this->history[1]['request']->getBody();
        $this->assertStringContainsString('name="lyrics_file"; filename="lyrics.txt"', $body);
        $this->assertStringContainsString('name="instrumental"', $body);
        $this->assertMatchesRegularExpression('/name="instrumental"[^\r\n]*\r\n[^\r\n]+\r\n\r\ntrue/', $body);
    }

    public function testMusicQueryDownloadAndVoice(): void
    {
        $c = $this->client([$this->jsonResponse([]), new Response(200, ['Content-Type' => 'audio/mpeg'], 'MP3'), $this->jsonResponse([]), $this->jsonResponse([])]);
        $c->music->list(['liked' => true, 'limit' => 2, 'q' => null]);
        $this->assertSame('/songs?liked=true&limit=2', $this->last(0)[1]);
        $this->assertSame('MP3', $c->music->download('s 1'));
        $this->assertSame('/songs/s%201/download', $this->last(1)[1]);
        $c->music->createVoice('Me', 'RIFFxxxxWAVE', 'ref.wav');
        $body = (string) $this->history[2]['request']->getBody();
        $this->assertStringContainsString('name="voice_reference"; filename="ref.wav"', $body);
        $this->assertMatchesRegularExpression('/name="consent"[^\r\n]*\r\n[^\r\n]+\r\n\r\ntrue/', $body);
        $c->music->update('s', ['liked' => true]);
        $this->assertSame(['PATCH', '/songs/s'], array_slice($this->last(3), 0, 2));
    }

    public function testMusicWaitForCompletion(): void
    {
        $gen = $this->jsonResponse(['data' => ['status' => 'generating']]);
        $c = $this->client([$gen, $gen, $this->jsonResponse(['data' => ['status' => 'succeeded', 'id' => 's']])]);
        $song = $c->music->waitForCompletion('s', ['intervalMs' => 1]);
        $this->assertSame('succeeded', $song['status']);
        $this->assertCount(3, $this->history);
    }

    public function testMusicWaitTimeout(): void
    {
        $gen = $this->jsonResponse(['data' => ['status' => 'generating']]);
        $c = $this->client(array_fill(0, 50, $gen));
        try {
            $c->music->waitForCompletion('s', ['intervalMs' => 1, 'timeoutMs' => 5]);
            $this->fail('expected timeout');
        } catch (SixtyDBException $e) {
            $this->assertSame('TIMEOUT', $e->getErrorCode());
        }
    }

    public function testAgentsUnwrapAndQueryNames(): void
    {
        $c = $this->client([
            $this->jsonResponse(['data' => ['calls' => [], 'total' => 0]]),
            $this->jsonResponse(['data' => ['id' => 'l1']]),
            $this->jsonResponse(['data' => ['id' => 'l1']]),
            $this->jsonResponse(['data' => ['id' => 'c1']]),
            $this->jsonResponse(['data' => ['url' => 'wss://x']]),
        ]);
        $this->assertSame(['calls' => [], 'total' => 0], $c->agents->calls->list(['agentId' => 'a1', 'status' => 'completed', 'from' => '2026-01-01', 'limit' => 3]));
        $this->assertSame('/agents/calls?agent_id=a1&status=completed&from=2026-01-01&limit=3', $this->last(0)[1]);
        $c->agents->phoneLines->create(['kind' => 'byo', 'name' => 'n', 'callerId' => '+1', 'inboundNumber' => null, 'agentId' => 'a']);
        $this->assertSame(['kind' => 'byo', 'name' => 'n', 'caller_id' => '+1', 'inbound_number' => null, 'agent_id' => 'a'], $this->last(1)[2]);
        $c->agents->phoneLines->update('l1', ['agentId' => null]);
        $this->assertSame(['agent_id' => null], $this->last(2)[2]);
        $c->agents->call('a1', ['to' => '+1', 'phoneLineId' => 'l1', 'metadata' => ['k' => 'v']]);
        $this->assertSame(['POST', '/agents/a1/calls', ['to' => '+1', 'phone_line_id' => 'l1', 'metadata' => ['k' => 'v']]], $this->last(3));
        $this->assertSame('wss://x', $c->agents->streamCall('a1')['url']);
        $this->assertSame('/agents/a1/stream-calls', $this->last(4)[1]);
        $this->assertSame('{}', (string) $this->history[4]['request']->getBody());
    }

    public function testAgentsMiscPathsAndRecording(): void
    {
        $ok = $this->jsonResponse(['data' => []]);
        $c = $this->client([$ok, $ok, $ok, new Response(200, [], 'WAVBYTES'), new Response(200, [], 'WAV2'), $ok]);
        $c->agents->rotateWebhookSecret('a');
        $c->agents->toolEvents('a');
        $c->agents->calls->transcript('c');
        $this->assertSame('WAVBYTES', $c->agents->calls->recording('c', true));
        $this->assertSame('/agents/calls/c/recording?mix=1', $this->last(3)[1]);
        $this->assertSame('WAV2', $c->agents->calls->recording('c'));
        $this->assertSame('/agents/calls/c/recording', $this->last(4)[1]);
        $c->agents->calls->deleteRecording('c');
        $this->assertSame('POST /agents/a/rotate-webhook-secret', implode(' ', array_slice($this->last(0), 0, 2)));
        $this->assertSame('/agents/a/tool-events', $this->last(1)[1]);
    }

    public function testAgentsErrorShape(): void
    {
        $c = $this->client([$this->jsonResponse(['success' => false, 'code' => 'POLICY_DENY', 'message' => 'no scope'], 403)]);
        try {
            $c->agents->list();
            $this->fail('expected error');
        } catch (SixtyDBException $e) {
            $this->assertSame([403, 'POLICY_DENY', 'no scope'], [$e->getStatus(), $e->getErrorCode(), $e->getMessage()]);
        }
    }

    public function testCallWaitRetriesTransientThenCompletes(): void
    {
        $c = $this->client([
            $this->jsonResponse(['message' => 'busy'], 429),
            $this->jsonResponse([], 503),
            $this->jsonResponse(['data' => ['status' => 'in_progress']]),
            $this->jsonResponse(['data' => ['status' => 'completed']]),
        ]);
        $call = $c->agents->calls->waitForCompletion('c', ['intervalMs' => 1]);
        $this->assertSame('completed', $call['status']);
        $this->assertCount(4, $this->history);
    }

    public function testCallWaitFailsFastOnClientErrorAndTimesOut(): void
    {
        $c = $this->client([$this->jsonResponse(['code' => 'POLICY_DENY'], 403)]);
        try {
            $c->agents->calls->waitForCompletion('c', ['intervalMs' => 1]);
            $this->fail('expected error');
        } catch (SixtyDBException $e) {
            $this->assertSame(403, $e->getStatus());
        }
        $q = $this->jsonResponse(['data' => ['status' => 'queued']]);
        $c = $this->client(array_fill(0, 50, $q));
        try {
            $c->agents->calls->waitForCompletion('c', ['intervalMs' => 1, 'timeoutMs' => 5]);
            $this->fail('expected timeout');
        } catch (SixtyDBException $e) {
            $this->assertSame('TIMEOUT', $e->getErrorCode());
        }
    }

    public function testDialerPaths(): void
    {
        $ok = $this->jsonResponse(['success' => true]);
        $c = $this->client(array_fill(0, 14, $ok));
        $c->dialer->status();
        $c->dialer->numbers->search();
        $c->dialer->numbers->setCallerId('+1555');
        $c->dialer->calls->list(['limit' => 2]);
        $c->dialer->calls->reserve(['to' => '+1', 'from' => '+2', 'sipCallId' => 'sip1']);
        $c->dialer->recordings->list(['e164' => '+1', 'status' => 'done']);
        $c->dialer->recordings->playbackUrl('r 1');
        $c->dialer->recordings->setEnabled(true);
        $c->dialer->billing->usage(['limit' => 5]);
        $c->dialer->webhooks->create(['name' => 'w', 'url' => 'https://x', 'event_types' => ['webhook.test']]);
        $c->dialer->webhooks->update('w1', ['status' => 'paused']);
        $c->dialer->webhooks->rotateSecret('w1');
        $c->dialer->webhooks->test('w1');
        $c->dialer->webhooks->deliveries('w1');
        $got = array_map(fn ($i) => implode(' ', array_slice($this->last($i), 0, 2)), range(0, 13));
        $this->assertSame([
            'GET /dialer/status', 'GET /dialer/pool', 'PUT /dialer/caller-id', 'GET /dialer/logs?limit=2', 'POST /dialer/calls',
            'GET /dialer/recordings?e164=%2B1&status=done', 'POST /dialer/recordings/r%201/playback-url', 'PUT /dialer/recording',
            'GET /dialer/usage?limit=5', 'POST /dialer/webhooks', 'PATCH /dialer/webhooks/w1', 'POST /dialer/webhooks/w1/rotate-secret',
            'POST /dialer/webhooks/w1/test', 'GET /dialer/webhooks/w1/deliveries',
        ], $got);
        $this->assertSame(['caller_id' => '+1555'], $this->last(2)[2]);
        $this->assertSame(['to' => '+1', 'from' => '+2', 'sip_call_id' => 'sip1'], $this->last(4)[2]);
        $this->assertSame(['enabled' => true], $this->last(7)[2]);
    }
}
