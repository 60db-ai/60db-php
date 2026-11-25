<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Live;

use PHPUnit\Framework\TestCase;
use SixtyDB\Client;
use SixtyDB\SixtyDBException;

/** Phase 2 live scenarios (no purchases, calls, voice or song creation). Skipped without SIXTYDB_API_KEY. */
final class LivePhase2Test extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $key = (string) getenv('SIXTYDB_API_KEY');
        if ($key === '') {
            $this->markTestSkipped('SIXTYDB_API_KEY not set');
        }
        $this->client = new Client($key);
    }

    private const QUESTIONS = [
        'sentiment' => ['type' => 'choice', 'instructions' => 'Overall sentiment of the text.',
            'criteria' => ['positive' => 'Happy or satisfied', 'negative' => 'Unhappy', 'unknown' => 'Cannot tell']],
    ];

    public function testJudgeInlineExtractAndReadOnly(): void
    {
        $j = $this->client->judge;
        $this->assertNotEmpty($j->models());
        $this->assertNotNull($j->health());
        $this->assertNotNull($j->usage());
        $eval = $j->evaluate(['state' => 'I love this product, it works great!', 'questions' => self::QUESTIONS, 'save' => false]);
        $this->assertNotEmpty($eval);
        $ext = $j->extract([
            'text' => 'Book a table for two tomorrow',
            'schema' => ['intents' => ['book' => 'Make a reservation'], 'operations' => ['create' => 'Create something'], 'entities' => ['party_size' => 'Number of people']],
            'save' => false,
        ]);
        $this->assertNotEmpty($ext);
        $this->assertArrayHasKey('runs', $j->runs->list(['limit' => 5]));
    }

    public function testJudgeRubricLifecycleAndRuns(): void
    {
        $j = $this->client->judge;
        $rubric = $j->rubrics->create(['name' => 'php-sdk-test-' . bin2hex(random_bytes(3)), 'questions' => self::QUESTIONS]);
        $id = (string) ($rubric['id'] ?? $rubric['hash_id'] ?? '');
        $this->assertNotSame('', $id, json_encode($rubric));
        $runId = null;
        try {
            $this->assertNotEmpty($j->rubrics->get($id));
            $j->rubrics->update($id, ['description' => 'updated by php sdk test']);
            $ids = array_map(fn ($r) => (string) ($r['id'] ?? $r['hash_id'] ?? ''), $j->rubrics->list());
            $this->assertContains($id, $ids);
            $run = $j->evaluate(['state' => 'Terrible service, never again.', 'rubric_id' => $id, 'label' => 'php-sdk-test']);
            $runId = (string) ($run['id'] ?? $run['run_id'] ?? $run['evaluation_id'] ?? '');
            if ($runId !== '') {
                $this->assertNotEmpty($j->runs->get($runId));
                $this->assertArrayHasKey('pagination', $j->runs->list(['rubric_id' => $id]));
            } else {
                $listed = $j->runs->list(['rubric_id' => $id, 'limit' => 5])['runs'] ?? [];
                $runId = (string) ($listed[0]['id'] ?? '');
            }
        } finally {
            if (!empty($runId)) {
                $j->runs->delete($runId);
            }
            $j->rubrics->delete($id);
        }
    }

    public function testMusicReadOnly(): void
    {
        $m = $this->client->music;
        $this->assertNotNull($m->health());
        $this->assertIsArray($m->listVoices());
        $list = $m->list(['limit' => 20]);
        $songs = $list['data']['songs'] ?? $list['data'] ?? [];
        $song = null;
        foreach ($songs as $s) {
            if (in_array($s['status'] ?? '', ['succeeded', 'ready'], true)) {
                $song = $s;
                break;
            }
        }
        if ($song === null) {
            $this->markTestIncomplete('No existing succeeded song to get/download');
        }
        $id = (string) ($song['id'] ?? $song['song_id']);
        $this->assertNotEmpty($m->get($id));
        $mp3 = $m->download($id);
        $this->assertGreaterThan(1000, strlen($mp3));
    }

    public function testDialerReadsAndWebhookLifecycle(): void
    {
        $d = $this->client->dialer;
        foreach ([fn () => $d->status(), fn () => $d->numbers->list(), fn () => $d->calls->list(['limit' => 5]),
            fn () => $d->calls->live(), fn () => $d->recordings->list(['limit' => 5]), fn () => $d->billing->subscriptions(),
            fn () => $d->billing->usage(['limit' => 5]), fn () => $d->webhooks->list()] as $i => $call) {
            $this->assertIsArray($call(), "dialer read #$i");
        }
        $created = $d->webhooks->create([
            'name' => 'php_sdk_' . bin2hex(random_bytes(4)),
            'url' => 'https://example.com/60db-php-sdk-test',
            'event_types' => ['webhook.test', 'call.completed'],
        ]);
        $id = (string) ($created['data']['id'] ?? $created['data']['hash_id'] ?? $created['id'] ?? '');
        $this->assertNotSame('', $id, json_encode($created));
        try {
            $this->assertIsArray($d->webhooks->update($id, ['description' => 'php sdk test']));
            $this->assertIsArray($d->webhooks->rotateSecret($id));
            $this->assertIsArray($d->webhooks->test($id));
            $this->assertIsArray($d->webhooks->deliveries($id));
        } finally {
            $d->webhooks->delete($id);
        }
    }

    public function testAgentsReturnPolicyDeny(): void
    {
        foreach ([fn () => $this->client->agents->list(), fn () => $this->client->agents->get('does-not-matter')] as $call) {
            try {
                $call();
                $this->fail('agents call should be denied');
            } catch (SixtyDBException $e) {
                $this->assertSame(403, $e->getStatus());
                $this->assertSame('POLICY_DENY', $e->getErrorCode());
            }
        }
    }
}
