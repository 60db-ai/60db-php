<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Upload;

/** Voices, STT/TTS languages and models. All methods return the full JSON body. */
final class Voices
{
    public function __construct(private Http $http)
    {
    }

    /** @return array<mixed> */
    public function getVoices(): array
    {
        return $this->http->json('GET', '/voices');
    }

    /** @return array<mixed> */
    public function getVoice(string $id): array
    {
        return $this->http->json('GET', '/voices/' . rawurlencode($id));
    }

    /** @return array<mixed> */
    public function getMyVoices(): array
    {
        return $this->http->json('GET', '/myvoices');
    }

    /**
     * @param list<string|resource> $files paths, stream resources, or raw bytes
     * @return array<mixed>
     */
    public function createVoice(string $name, array $files): array
    {
        $parts = [['name' => 'name', 'contents' => $name]];
        foreach ($files as $i => $file) {
            [$bytes, $pathName] = Upload::read($file);
            $parts[] = ['name' => 'files', 'contents' => $bytes, 'filename' => $pathName ?? "voice-$i.wav"];
        }
        return $this->http->json('POST', '/voices', ['multipart' => $parts]);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>
     */
    public function updateVoice(string $id, array $params): array
    {
        return $this->http->json('PUT', '/voices/' . rawurlencode($id), ['json' => $params]);
    }

    /** @return array<mixed> */
    public function deleteVoice(string $id): array
    {
        return $this->http->json('DELETE', '/voices/' . rawurlencode($id));
    }

    /** STT languages (`data.languages[]`). @return array<mixed> */
    public function getLanguages(): array
    {
        return $this->http->json('GET', '/stt/languages');
    }

    /** TTS languages (`data[]`). @return array<mixed> */
    public function getTTSLanguages(): array
    {
        return $this->http->json('GET', '/tts/languages');
    }

    /** @return array<mixed> */
    public function getTTSModels(): array
    {
        return $this->http->json('GET', '/tts/models');
    }

    /** @return array<mixed> */
    public function getSTTModels(): array
    {
        return $this->http->json('GET', '/stt/models');
    }
}
