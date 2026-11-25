<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;
use SixtyDB\SixtyDBException;
use SixtyDB\Upload;

/** Music (songs). Methods return the full JSON body unless noted. */
final class Music
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Create a song (async). With `lyrics_file` (path/resource/bytes) the request is multipart.
     *
     * @param array<string,mixed> $params prompt, title, tags, voice_id, target_duration, lyrics_file, ...
     * @return array<mixed>
     */
    public function create(array $params): array
    {
        if (!empty($params['lyrics_file'])) {
            [$bytes, $name] = Upload::read($params['lyrics_file']);
            $parts = Params::fields(array_diff_key($params, ['lyrics_file' => 1]));
            $parts[] = ['name' => 'lyrics_file', 'contents' => $bytes, 'filename' => $name ?? 'lyrics.txt'];
            return $this->http->json('POST', '/songs', ['multipart' => $parts]);
        }
        unset($params['lyrics_file']);
        return $this->http->json('POST', '/songs', ['json' => Params::body($params)]);
    }

    /** @param array<string,mixed> $params limit, offset, q, status, type, liked, sort @return array<mixed> */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/songs', ['query' => Params::query($params)]);
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/songs/' . rawurlencode($id));
    }

    /** @param array<string,mixed> $params liked?, played? @return array<mixed> */
    public function update(string $id, array $params): array
    {
        return $this->http->json('PATCH', '/songs/' . rawurlencode($id), ['json' => Params::body($params)]);
    }

    /** @return array<mixed> */
    public function delete(string $id): array
    {
        return $this->http->json('DELETE', '/songs/' . rawurlencode($id));
    }

    /** MP3 bytes. */
    public function download(string $id): string
    {
        return (string) $this->http->send('GET', '/songs/' . rawurlencode($id) . '/download')->getBody();
    }

    /**
     * Poll until the song is `succeeded` or `failed`; returns the unwrapped song.
     *
     * @param array{intervalMs?:int,timeoutMs?:int} $options defaults 4000 / 600000
     * @return array<mixed>
     */
    public function waitForCompletion(string $id, array $options = []): array
    {
        $interval = $options['intervalMs'] ?? 4000;
        $timeout = $options['timeoutMs'] ?? 600000;
        $start = hrtime(true);
        while (true) {
            $body = $this->get($id);
            $song = is_array($body['data'] ?? null) ? $body['data'] : $body;
            if (in_array($song['status'] ?? null, ['succeeded', 'failed'], true)) {
                return $song;
            }
            if ((hrtime(true) - $start) / 1e6 >= $timeout) {
                throw new SixtyDBException("Timed out waiting for song $id to complete", 0, 'TIMEOUT');
            }
            usleep($interval * 1000);
        }
    }

    /** @return array<mixed> */
    public function listVoices(): array
    {
        return $this->http->json('GET', '/songs/voices');
    }

    /**
     * Register a voice from a reference recording (sends consent=true).
     *
     * @param string|resource $file path, stream resource, or bytes
     * @return array<mixed>
     */
    public function createVoice(string $name, $file, ?string $filename = null): array
    {
        [$bytes, $pathName] = Upload::read($file);
        return $this->http->json('POST', '/songs/voices', ['multipart' => [
            ['name' => 'name', 'contents' => $name],
            ['name' => 'voice_reference', 'contents' => $bytes, 'filename' => $filename ?? $pathName ?? 'voice.wav'],
            ['name' => 'consent', 'contents' => 'true'],
        ]]);
    }

    /** @return array<mixed> */
    public function health(): array
    {
        return $this->http->json('GET', '/songs/health');
    }
}
