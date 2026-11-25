<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SixtyDB\Audio;

final class AudioTest extends TestCase
{
    public function testPcmToWavHeader(): void
    {
        $wav = Audio::pcmToWav(str_repeat("\x01\x00", 100), 24000);
        $this->assertSame(244, strlen($wav));
        $this->assertSame('RIFF', substr($wav, 0, 4));
        $this->assertSame(236, unpack('V', substr($wav, 4, 4))[1]);
        $this->assertSame('WAVEfmt ', substr($wav, 8, 8));
        $this->assertSame(24000, unpack('V', substr($wav, 24, 4))[1]);
        $this->assertSame(48000, unpack('V', substr($wav, 28, 4))[1]);
        $this->assertSame(16, unpack('v', substr($wav, 34, 2))[1]);
        $this->assertSame('data', substr($wav, 36, 4));
        $this->assertSame(200, unpack('V', substr($wav, 40, 4))[1]);
    }

    /** @dataProvider mimeCases */
    public function testSniffMime(string $bytes, ?string $name, string $expected): void
    {
        $this->assertSame($expected, Audio::sniffMime($bytes, $name));
    }

    public static function mimeCases(): array
    {
        return [
            'wav' => ["RIFF\x00\x00\x00\x00WAVEfmt ", null, 'audio/wav'],
            'mp3 id3' => ["ID3\x04\x00", null, 'audio/mpeg'],
            'mp3 frame' => ["\xFF\xFB\x90\x00", null, 'audio/mpeg'],
            'ogg' => ['OggS' . "\x00", null, 'audio/ogg'],
            'flac' => ['fLaC' . "\x00", null, 'audio/flac'],
            'mp4' => ["\x00\x00\x00\x20ftypM4A ", null, 'audio/mp4'],
            'webm' => ["\x1A\x45\xDF\xA3\x00", null, 'audio/webm'],
            'ext fallback' => ['xxxxxxxxxxxx', 'clip.FLAC', 'audio/flac'],
            'unknown' => ['xxxxxxxxxxxx', null, 'application/octet-stream'],
        ];
    }
}
