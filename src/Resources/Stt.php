<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Audio;
use SixtyDB\Http;
use SixtyDB\Upload;

final class Stt
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Transcribe audio.
     *
     * @param string|resource $file path, stream resource, or raw audio bytes
     * @return array<mixed>
     */
    public function speechToText($file, ?string $language = null, bool $diarize = false, ?string $context = null, ?string $filename = null): array
    {
        [$bytes, $pathName] = Upload::read($file);
        $mime = Audio::sniffMime($bytes, $filename ?? $pathName);
        $name = $filename ?? $pathName ?? 'audio.' . Audio::extensionFor($mime);
        $parts = [[
            'name' => 'file',
            'contents' => $bytes,
            'filename' => $name,
            'headers' => ['Content-Type' => $mime],
        ]];
        if ($language !== null && $language !== '') {
            $parts[] = ['name' => 'language', 'contents' => $language];
        }
        if ($diarize) {
            $parts[] = ['name' => 'diarize', 'contents' => 'true'];
        }
        if ($context !== null && trim($context) !== '') {
            $parts[] = ['name' => 'context', 'contents' => trim($context)];
        }
        return $this->http->json('POST', '/stt', ['multipart' => $parts, 'timeout' => 300]);
    }
}
