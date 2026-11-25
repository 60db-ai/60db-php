<?php

declare(strict_types=1);

namespace SixtyDB;

/** Normalises a file argument (path, stream resource, or raw bytes) for multipart uploads. */
final class Upload
{
    /**
     * @param string|resource $file
     * @return array{0:string,1:?string} [bytes, filename from path or null]
     */
    public static function read($file): array
    {
        if (is_resource($file)) {
            $bytes = stream_get_contents($file);
            if ($bytes === false) {
                throw new \InvalidArgumentException('Could not read stream resource');
            }
            $uri = stream_get_meta_data($file)['uri'] ?? null;
            return [$bytes, is_string($uri) ? basename($uri) : null];
        }
        if (!is_string($file)) {
            throw new \InvalidArgumentException('File must be a path, stream resource, or byte string');
        }
        if (strlen($file) < 4096 && !str_contains($file, "\0") && is_file($file)) {
            $bytes = file_get_contents($file);
            if ($bytes === false) {
                throw new \InvalidArgumentException("Could not read file: $file");
            }
            return [$bytes, basename($file)];
        }
        return [$file, null];
    }
}
