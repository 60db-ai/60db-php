<?php

declare(strict_types=1);

namespace SixtyDB;

final class Audio
{
    /** Wrap 16-bit little-endian PCM in a 44-byte RIFF/WAV header. */
    public static function pcmToWav(string $pcm, int $sampleRate = 48000, int $channels = 1): string
    {
        $len = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $len) . 'WAVE'
            . 'fmt ' . pack('VvvVVvv', 16, 1, $channels, $sampleRate, $sampleRate * $channels * 2, $channels * 2, 16)
            . 'data' . pack('V', $len) . $pcm;
    }

    private const EXT = [
        'wav' => 'audio/wav', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'flac' => 'audio/flac', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'webm' => 'audio/webm',
        'opus' => 'audio/ogg', 'aac' => 'audio/aac',
    ];

    /** Sniff the audio MIME type from magic bytes, then the filename extension. */
    public static function sniffMime(string $bytes, ?string $filename = null): string
    {
        if (strlen($bytes) >= 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WAVE') {
            return 'audio/wav';
        }
        if (str_starts_with($bytes, 'ID3') || (strlen($bytes) >= 2 && ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE0) === 0xE0)) {
            return 'audio/mpeg';
        }
        if (str_starts_with($bytes, 'OggS')) {
            return 'audio/ogg';
        }
        if (str_starts_with($bytes, 'fLaC')) {
            return 'audio/flac';
        }
        if (strlen($bytes) >= 8 && substr($bytes, 4, 4) === 'ftyp') {
            return 'audio/mp4';
        }
        if (str_starts_with($bytes, "\x1A\x45\xDF\xA3")) {
            return 'audio/webm';
        }
        $ext = $filename !== null ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : '';
        return self::EXT[$ext] ?? 'application/octet-stream';
    }

    /** File extension for a sniffed MIME type (for default filenames). */
    public static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'audio/wav' => 'wav', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/flac' => 'flac',
            'audio/mp4' => 'm4a', 'audio/webm' => 'webm', default => 'bin',
        };
    }
}
