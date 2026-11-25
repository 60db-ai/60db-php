<?php

declare(strict_types=1);

namespace SixtyDB;

use Psr\Http\Message\StreamInterface;

/** Reads a streamed response body as raw-byte lines (no charset assumptions). */
final class LineReader
{
    /** @return \Generator<int,string> non-empty, trimmed lines */
    public static function lines(StreamInterface $stream): \Generator
    {
        $buffer = '';
        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    continue;
                }
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($line !== '') {
                        yield $line;
                    }
                }
            }
            $rest = trim($buffer);
            if ($rest !== '') {
                yield $rest;
            }
        } finally {
            $stream->close();
        }
    }
}
