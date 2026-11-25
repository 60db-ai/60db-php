<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Live;

use GuzzleHttp\Client as Guzzle;
use PHPUnit\Framework\TestCase;
use SixtyDB\Audio;
use SixtyDB\Client;
use SixtyDB\SixtyDBException;

/** Live scenarios against the production API. Skipped unless SIXTYDB_API_KEY is set. */
final class LiveTest extends TestCase
{
    private static string $key = '';
    private Client $client;

    public static function setUpBeforeClass(): void
    {
        self::$key = (string) getenv('SIXTYDB_API_KEY');
    }

    protected function setUp(): void
    {
        if (self::$key === '') {
            $this->markTestSkipped('SIXTYDB_API_KEY not set');
        }
        $this->client = new Client(self::$key);
    }

    private function voiceId(): string
    {
        return (string) $this->client->getVoices()['data'][0]['voice_id'];
    }

    private function assertWav(string $wav): void
    {
        $this->assertSame('RIFF', substr($wav, 0, 4));
        $this->assertSame('WAVE', substr($wav, 8, 4));
        $this->assertGreaterThan(1000, strlen($wav));
    }

    public function testTts(): void
    {
        $wav = $this->client->textToSpeech('Hello from the PHP SDK.', $this->voiceId());
        $this->assertWav($wav);
    }

    public function testTtsStreamAndPcmToWav(): void
    {
        $pcm = '';
        foreach ($this->client->textToSpeechStream('Streaming test.', $this->voiceId(), ['sample_rate' => 24000]) as $chunk) {
            $pcm .= $chunk;
        }
        $this->assertGreaterThan(1000, strlen($pcm));
        $this->assertWav(Audio::pcmToWav($pcm, 24000));
    }

    public function testSttRoundTrip(): void
    {
        $wav = $this->client->textToSpeech('Hello world, this is a speech to text test.', $this->voiceId());
        $res = $this->client->speechToText($wav, 'en');
        $this->assertArrayHasKey('text', $res);
        $this->assertStringContainsStringIgnoringCase('hello', (string) $res['text']);
    }

    public function testVoicesLanguagesModels(): void
    {
        $voices = $this->client->getVoices();
        $this->assertNotEmpty($voices['data']);
        $id = $voices['data'][0]['voice_id'];
        $this->assertNotEmpty($this->client->getVoice($id));
        $this->assertIsArray($this->client->getMyVoices());
        $this->assertNotEmpty($this->client->getLanguages()['data']['languages']);
        $this->assertNotEmpty($this->client->getTTSLanguages()['data']);
        $this->assertNotEmpty($this->client->getTTSModels());
        $this->assertNotEmpty($this->client->getSTTModels());
    }

    public function testAccountGets(): void
    {
        foreach (['getProfile', 'getWorkspaces', 'getPlans', 'getCurrentPlan', 'getUsage', 'getApiKeys', 'getWebhooks'] as $m) {
            $this->assertIsArray($this->client->$m(), $m);
        }
    }

    public function testChatAndStream(): void
    {
        $messages = [['role' => 'user', 'content' => 'Reply with the single word: pong']];
        $res = $this->client->chat->completion(['messages' => $messages]);
        $this->assertNotSame('', (string) $res['choices'][0]['message']['content']);

        $text = '';
        $chatId = null;
        foreach ($this->client->chat->stream(['messages' => $messages]) as $chunk) {
            $chatId ??= $chunk['chat_id'] ?? null;
            $text .= $chunk['choices'][0]['delta']['content'] ?? '';
        }
        $this->assertNotSame('', $text);
    }

    public function testMemoryWithTemporaryCollection(): void
    {
        $http = new Guzzle(['base_uri' => 'https://api.60db.ai', 'headers' => ['Authorization' => 'Bearer ' . self::$key], 'http_errors' => false]);
        $created = json_decode((string) $http->post('/memory/collections', ['json' => ['collection_id' => 'sdk-test-php-' . bin2hex(random_bytes(3)), 'label' => 'PHP SDK test']])->getBody(), true);
        $cid = $created['data']['collection_id'] ?? null;
        $this->assertNotNull($cid, 'collection create failed: ' . json_encode($created));
        try {
            $ing = $this->client->memory->ingest('The PHP SDK test fact: the capital of Freedonia is Fredville.', ['collection' => $cid, 'infer' => false]);
            $this->assertTrue($ing['success'] ?? false);
            $this->assertArrayHasKey('data', $this->client->memory->search('capital of Freedonia', ['collection' => $cid]));
            $ctx = $this->client->memory->context('capital of Freedonia', ['collection' => $cid]);
            $this->assertIsString($ctx['data']['prompt_ready']);
            $doc = $this->client->memory->extractDocument('PHP SDK document: Freedonia exports tulips.', 'sdk-test.txt', ['collection' => $cid]);
            $this->assertTrue($doc['success'] ?? false);
            $this->assertIsArray($this->client->memory->usage());
        } finally {
            $http->delete('/memory/collections/' . rawurlencode((string) $cid));
        }
    }

    public function testErrorCases(): void
    {
        try {
            $this->client->textToSpeech('');
            $this->fail('empty TTS text must fail');
        } catch (SixtyDBException $e) {
            $this->assertSame(400, $e->getStatus());
        }

        try {
            (new Client('sk_live_invalid_key_xyz'))->getProfile();
            $this->fail('invalid key must fail');
        } catch (SixtyDBException $e) {
            $this->assertSame(401, $e->getStatus());
        }

        $this->expectException(SixtyDBException::class);
        $this->client->chat->completion(['model' => 'no-such-model-xyz', 'messages' => [['role' => 'user', 'content' => 'x']]]);
    }
}
