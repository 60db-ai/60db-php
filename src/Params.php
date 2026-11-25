<?php

declare(strict_types=1);

namespace SixtyDB;

/** Small request-parameter helpers. */
final class Params
{
    /** Query array: nulls dropped, booleans sent as "true"/"false". */
    public static function query(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if ($v !== null) {
                $out[$k] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
            }
        }
        return $out;
    }

    /** Rename keys that are present (camelCase to snake_case); other keys pass through unchanged. */
    public static function rename(array $params, array $map): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            $out[$map[$k] ?? $k] = $v;
        }
        return $out;
    }

    /** JSON body that encodes an empty array as `{}`. */
    public static function body(array $params): array|\stdClass
    {
        return $params === [] ? new \stdClass() : $params;
    }

    /** Multipart text parts from scalar fields (booleans stringified, nulls skipped). */
    public static function fields(array $fields): array
    {
        $parts = [];
        foreach ($fields as $k => $v) {
            if ($v !== null) {
                $parts[] = ['name' => (string) $k, 'contents' => is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? (string) $v : json_encode($v))];
            }
        }
        return $parts;
    }
}
