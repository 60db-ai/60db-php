<?php

declare(strict_types=1);

namespace SixtyDB;

/** Raised for every non-2xx response, transport failure, or in-stream error. */
class SixtyDBException extends \RuntimeException
{
    /**
     * @param array<mixed>|string|null $body Parsed JSON body (or raw text) of the failed response.
     */
    public function __construct(
        string $message,
        private int $status = 0,
        private ?string $errorCode = null,
        private ?string $field = null,
        private array|string|null $body = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    /** @return array<mixed>|string|null */
    public function getBody(): array|string|null
    {
        return $this->body;
    }

    /** Build from an HTTP error response body. */
    public static function fromResponse(int $status, string $raw): self
    {
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            return new self("HTTP $status", $status, null, null, $raw === '' ? null : $raw);
        }
        $msg = $body['message'] ?? (is_string($body['error'] ?? null) ? $body['error'] : null);
        $code = $body['code'] ?? null;
        $field = $body['field'] ?? null;
        return new self(
            is_string($msg) && $msg !== '' ? $msg : "HTTP $status",
            $status,
            is_scalar($code) ? (string) $code : null,
            is_scalar($field) ? (string) $field : null,
            $body,
        );
    }
}
