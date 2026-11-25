<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\LineReader;
use SixtyDB\SixtyDBException;

final class Tts
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Synthesize speech; returns the bytes of a complete WAV file.
     *
     * @param array<string,mixed> $params speed, sample_rate, audio_enhance, ...
     */
    public function textToSpeech(string $text, ?string $voiceId = null, array $params = []): string
    {
        $body = Http::clean(['text' => $text, 'voice_id' => $voiceId] + $params);
        $res = $this->http->send('POST', '/tts-synthesize', ['json' => $body]);
        $type = strtolower($res->getHeaderLine('Content-Type'));
        if (str_contains($type, 'audio/') || str_contains($type, 'octet-stream')) {
            return (string) $res->getBody();
        }
        $data = Http::decode($res);
        if (($data['success'] ?? true) === false) {
            throw new SixtyDBException((string) ($data['message'] ?? 'TTS failed'), $res->getStatusCode(), null, null, $data);
        }
        foreach ([$data['audio_base64'] ?? null, $data['backendResponse']['audio_base64'] ?? null] as $b64) {
            if (is_string($b64) && $b64 !== '') {
                return (string) base64_decode($b64);
            }
        }
        if (is_string($data['audioContent'] ?? null) && $data['audioContent'] !== '') {
            // Fallback is headerless PCM (wrap with Audio::pcmToWav if a WAV is needed).
            return (string) base64_decode($data['audioContent']);
        }
        throw new SixtyDBException('TTS response contained no audio', $res->getStatusCode(), null, null, $data);
    }

    /**
     * Stream synthesized speech as raw PCM chunks (16-bit LE mono at `sample_rate`, default 48000).
     *
     * @param array<string,mixed> $params
     * @return \Generator<int,string>
     */
    public function textToSpeechStream(string $text, ?string $voiceId = null, array $params = []): \Generator
    {
        $body = Http::clean(['text' => $text, 'voice_id' => $voiceId] + $params) + ['stream' => true];
        $res = $this->http->send('POST', '/tts-synthesize', ['json' => $body, 'stream' => true, 'timeout' => 300]);
        foreach (LineReader::lines($res->getBody()) as $line) {
            $msg = json_decode($line, true);
            if (!is_array($msg)) {
                continue;
            }
            if (($msg['type'] ?? null) === 'error' || ($msg['success'] ?? null) === false) {
                throw new SixtyDBException((string) ($msg['message'] ?? 'TTS stream error'), $res->getStatusCode(), null, null, $msg);
            }
            if (($msg['type'] ?? null) === 'meta') {
                continue;
            }
            $audio = $msg['result']['audioContent'] ?? null;
            if (is_string($audio) && $audio !== '') {
                yield (string) base64_decode($audio);
            }
        }
    }
}
