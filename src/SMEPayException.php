<?php

declare(strict_types=1);

namespace SMEPay;

/**
 * Thrown for every failed SDK call.
 *
 * API errors: getErrorCode() is the API's "error" field (e.g. "duplicate_order_id"),
 * getStatusCode() the HTTP status and getRaw() the API reply.
 * SDK-side errors use the codes "invalid_config", "invalid_request", "network_error", "timeout"
 * and "invalid_response"; getStatusCode() is null when no reply was received.
 */
class SMEPayException extends \RuntimeException
{
    /** Keys that must never appear in an error, even if an API reply echoes them. */
    private const SENSITIVE_KEYS = ['access_token', 'client_secret', 'authorization'];

    private string $errorCode;

    private ?int $statusCode;

    private mixed $raw;

    public function __construct(
        string $errorCode,
        string $message,
        ?int $statusCode = null,
        mixed $raw = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->raw = self::redact($raw);
    }

    /** Machine-readable code, e.g. "duplicate_order_id" or "timeout". */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** HTTP status of the API reply, or null if no reply was received. */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /** The API reply (decoded JSON array, or a text excerpt), with secrets redacted. */
    public function getRaw(): mixed
    {
        return $this->raw;
    }

    private static function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $_) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $value[$key] = '[redacted]';
            }
        }

        return $value;
    }
}
