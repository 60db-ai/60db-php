<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SixtyDB\SixtyDBException;

final class ClientTest extends TestCase
{
    use MockedClientTrait;

    private function ndjson(array $lines): Response
    {
        return new Response(200, ['Content-Type' => 'application/x-ndjson'], implode("\n", array_map('json_encode', $lines)) . "\n");
    }

    private function sse(array $events, bool $done = true): Response
    {
        $out = '';
        foreach ($events as $e) {
            $out .= 'data: ' . (is_string($e) ? $e : json_encode($e)) . "\n\n";
        }
        return new Response(200, ['Content-Type' => 'text/event-stream'], $out . ($done ? "data: [DONE]\n\n" : ''));
    }

    public function testErrorMapping(): void
    {
        $c = $this->client([$this->jsonResponse(['message' => 'denied', 'code' => 'POLICY_DENY', 'field' => 'x'], 403), new Response(500, [], 'oops')]);
        try {
            $c->getProfile();
            $this->fail('expected exception');
        } catch (SixtyDBException $e) {
            $this->assertSame([403, 'denied', 'POLICY_DENY', 'x'], [$e->getStatus(), $e->getMessage(), $e->getErrorCode(), $e->getField()]);
            $this->assertSame('denied', $e->getBody()['message']);
        }
        try {
            $c->getProfile();
            $this->fail('expected exception');
        } catch (SixtyDBException $e) {
            $this->assertSame('HTTP 500', $e->getMessage());
            $this->assertSame('oops', $e->getBody());
        }
    }

    public function testAuthHeaderBaseUrlAndEncoding(): void
    {
        $c = $this->client([$this->jsonResponse(['success' => true])]);
        $c->getVoice('a b/c');
        $req = $this->history[0]['request'];
        $this->assertSame('Bearer test-key', $req->getHeaderLine('Authorization'));
        $this->assertSame('https://example.test/voices/a%20b%2Fc', (string) $req->getUri());
    }

    public function testTtsDecodesAudioBase64(): void
    {
        $wav = "RIFF\x00\x00\x00\x00WAVE";
        $c = $this->client([$this->jsonResponse(['success' => true, 'audio_base64' => base64_encode($wav)])]);
        $this->assertSame($wav, $c->textToSpeech('hi', 'v1', ['speed' => 1.1]));
        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame(['text' => 'hi', 'voice_id' => 'v1', 'speed' => 1.1], $body);
    }

    public function testTtsFailureAndAudioContentType(): void
    {
        $c = $this->client([
            $this->jsonResponse(['success' => false, 'message' => 'bad voice']),
            new Response(200, ['Content-Type' => 'audio/wav'], 'RAWBYTES'),
        ]);
        try {
            $c->textToSpeech('hi');
            $this->fail('expected exception');
        } catch (SixtyDBException $e) {
            $this->assertSame('bad voice', $e->getMessage());
        }
        $this->assertSame('RAWBYTES', $c->textToSpeech('hi'));
    }

    public function testTtsEmptyTextError(): void
    {
        $c = $this->client([$this->jsonResponse(['success' => false, 'message' => 'text is required'], 400)]);
        $this->expectException(SixtyDBException::class);
        $this->expectExceptionMessage('text is required');
        $c->textToSpeech('');
    }

    public function testTtsStreamParsesNdjson(): void
    {
        $c = $this->client([$this->ndjson([
            ['conditioning' => ['a' => 1]],
            ['type' => 'meta'],
            ['result' => ['audioContent' => base64_encode("\x01\x02")]],
            ['result' => ['audioContent' => base64_encode("\x03\x04")]],
        ])]);
        $chunks = iterator_to_array($c->textToSpeechStream('hi', null, ['sample_rate' => 24000]), false);
        $this->assertSame(["\x01\x02", "\x03\x04"], $chunks);
        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertTrue($body['stream']);
        $this->assertSame('/tts-synthesize', $this->history[0]['request']->getUri()->getPath());
    }

    public function testTtsStreamErrorLine(): void
    {
        $c = $this->client([$this->ndjson([
            ['result' => ['audioContent' => base64_encode('a')]],
            ['type' => 'error', 'message' => 'boom'],
        ])]);
        $got = [];
        try {
            foreach ($c->textToSpeechStream('hi') as $chunk) {
                $got[] = $chunk;
            }
            $this->fail('expected exception');
        } catch (SixtyDBException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame(['a'], $got);
    }

    public function testSttMultipartFieldFileAndMime(): void
    {
        $c = $this->client([$this->jsonResponse(['text' => 'hello'])]);
        $res = $c->speechToText("RIFF\x00\x00\x00\x00WAVEdata", 'en', true, '  ctx  ');
        $this->assertSame('hello', $res['text']);
        $body = (string) $this->history[0]['request']->getBody();
        $this->assertStringContainsString('name="file"; filename="audio.wav"', $body);
        $this->assertStringContainsString('Content-Type: audio/wav', $body);
        $this->assertStringContainsString('name="language"', $body);
        $this->assertMatchesRegularExpression('/name="diarize"[^\r\n]*\r\n[^\r\n]+\r\n\r\ntrue/', $body);
        $this->assertMatchesRegularExpression('/name="context"[^\r\n]*\r\n[^\r\n]+\r\n\r\nctx\r\n/', $body);
        $this->assertStringNotContainsString('name="audio"', $body);
    }

    public function testSttAcceptsPathAndResource(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stt') ?: '';
        file_put_contents($path, "OggS\x00\x00");
        $c = $this->client([$this->jsonResponse(['text' => 'a']), $this->jsonResponse(['text' => 'b'])]);
        $c->speechToText($path);
        $fh = fopen($path, 'rb');
        $c->speechToText($fh);
        fclose($fh);
        unlink($path);
        foreach ($this->history as $h) {
            $this->assertStringContainsString('Content-Type: audio/ogg', (string) $h['request']->getBody());
        }
    }

    public function testChatCompletionBody(): void
    {
        $c = $this->client([$this->jsonResponse(['choices' => [['message' => ['content' => 'Hi']]]])]);
        $res = $c->chat->completion(['messages' => [['role' => 'user', 'content' => 'yo']]]);
        $this->assertSame('Hi', $res['choices'][0]['message']['content']);
        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('vyas', $body['model']);
        $this->assertFalse($body['stream']);
    }

    public function testChatKeepsCustomModel(): void
    {
        $c = $this->client([$this->jsonResponse([])]);
        $c->chat->completion(['model' => 'custom-1', 'text' => 'fix me']);
        $this->assertSame('custom-1', json_decode((string) $this->history[0]['request']->getBody(), true)['model']);
    }

    public function testChatForcesStreamFlagAndKeepsModel(): void
    {
        $c = $this->client([$this->jsonResponse([]), $this->sse([['choices' => []]])]);
        $c->chat->completion(['model' => 'm1', 'stream' => true, 'messages' => [['role' => 'user', 'content' => 'x']]]);
        iterator_to_array($c->chat->stream(['stream' => false, 'messages' => [['role' => 'user', 'content' => 'x']]]));
        $this->assertFalse(json_decode((string) $this->history[0]['request']->getBody(), true)['stream']);
        $this->assertSame('m1', json_decode((string) $this->history[0]['request']->getBody(), true)['model']);
        $this->assertTrue(json_decode((string) $this->history[1]['request']->getBody(), true)['stream']);
    }

    public function testChatStreamErrorCarriesCode(): void
    {
        $c = $this->client([$this->sse([['type' => 'error', 'message' => 'x', 'code' => 'RATE_LIMIT']])]);
        try {
            iterator_to_array($c->chat->stream(['text' => 'x']));
            $this->fail('expected exception');
        } catch (SixtyDBException $e) {
            $this->assertSame('RATE_LIMIT', $e->getErrorCode());
        }
    }

    public function testTtsAudioContentFallbackIsHeaderlessPcm(): void
    {
        $c = $this->client([$this->jsonResponse(['audioContent' => base64_encode("\x01\x02\x03\x04")])]);
        $this->assertSame("\x01\x02\x03\x04", $c->textToSpeech('hi'));
    }

    public function testChatRequiresMessagesOrText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client([])->chat->completion(['model' => 'x']);
    }

    public function testChatStreamParsesSse(): void
    {
        $c = $this->client([$this->sse([
            ['type' => 'chat_id', 'chat_id' => 'c1'],
            ['choices' => [['delta' => ['content' => 'Hi']]]],
            ['choices' => [['delta' => ['content' => '!']]]],
            'not json',
        ])]);
        $text = '';
        foreach ($c->chat->stream(['messages' => [['role' => 'user', 'content' => 'x']]]) as $chunk) {
            $text .= $chunk['choices'][0]['delta']['content'] ?? '';
        }
        $this->assertSame('Hi!', $text);
    }

    public function testChatStreamInStreamError(): void
    {
        $inner = json_encode(['error' => ['message' => 'The model nope does not exist']]);
        $c = $this->client([$this->sse([['type' => 'error', 'error' => $inner, 'message' => 'generic']])]);
        $this->expectExceptionMessage('The model nope does not exist');
        iterator_to_array($c->chat->stream(['messages' => [['role' => 'user', 'content' => 'x']]]));
    }

    public function testMemoryEndpoints(): void
    {
        $ok = $this->jsonResponse(['success' => true, 'data' => []]);
        $c = $this->client([$ok, $ok, $ok, $ok, $ok]);
        $c->memory->ingest('remember', ['collection' => 'k']);
        $c->memory->search('q', ['max_results' => 3]);
        $c->memory->context('q');
        $c->memory->extractDocument('hello text', 'a.txt', ['title' => 'T', 'skip' => null]);
        $c->memory->usage();
        $paths = array_map(fn ($h) => $h['request']->getUri()->getPath(), $this->history);
        $this->assertSame(['/memory/ingest', '/memory/search', '/memory/context', '/memory/documents/extract', '/memory/usage'], $paths);
        $this->assertSame(['text' => 'remember', 'collection' => 'k'], json_decode((string) $this->history[0]['request']->getBody(), true));
        $doc = (string) $this->history[3]['request']->getBody();
        $this->assertStringContainsString('name="file"; filename="a.txt"', $doc);
        $this->assertStringContainsString('name="title"', $doc);
        $this->assertStringNotContainsString('name="skip"', $doc);
    }

    public function testFlatForwardingAndUnknownMethod(): void
    {
        $c = $this->client([$this->jsonResponse(['success' => true])]);
        $c->getTTSModels();
        $this->assertSame('/tts/models', $this->history[0]['request']->getUri()->getPath());
        $this->expectException(\BadMethodCallException::class);
        $c->nope();
    }
}
