<?php

declare(strict_types=1);

namespace SMEPay\Internal;

use SMEPay\SMEPayException;
use SMEPay\Version;

/**
 * JSON-over-HTTPS POST helper.
 *
 * @internal not part of the public API; may change without notice
 */
final class HttpClient
{
    /** Fixed maximum per request; deliberately not configurable. */
    public const TIMEOUT_SECONDS = 60;

    /** Longest slice of a non-JSON reply kept in SMEPayException::getRaw(). */
    private const RAW_TEXT_LIMIT = 500;

    /** Cap on the size of a reply. */
    private const MAX_REPLY_BYTES = 10 * 1024 * 1024;

    /**
     * POST $body as JSON and return the decoded reply object.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws SMEPayException on any failure
     */
    public static function post(
        string $baseUrl,
        string $path,
        #[\SensitiveParameter] array $body,
        #[\SensitiveParameter] ?string $token = null,
    ): array {
        try {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new SMEPayException('invalid_request', "Request body for {$path} could not be encoded as JSON: {$e->getMessage()}");
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: smepay-php/' . Version::VERSION,
        ];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $curl = curl_init($baseUrl . $path);
        if ($curl === false) {
            throw new SMEPayException('network_error', "Could not reach SMEPay ({$path}): curl_init failed");
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_MAXFILESIZE => self::MAX_REPLY_BYTES,
        ]);

        $reply = curl_exec($curl);
        if (!is_string($reply)) {
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new SMEPayException('timeout', "Request to {$path} timed out after " . self::TIMEOUT_SECONDS . 's');
            }
            throw new SMEPayException('network_error', "Could not reach SMEPay ({$path}): {$error}");
        }
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        try {
            $data = json_decode($reply, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SMEPayException(
                'invalid_response',
                "SMEPay returned a non-JSON reply (HTTP {$status}) for {$path}",
                $status,
                substr($reply, 0, self::RAW_TEXT_LIMIT),
            );
        }
        // Decoded twice: objects first, to tell a JSON object from a JSON list.
        $isObject = $data instanceof \stdClass;
        $decoded = json_decode($reply, true);

        if ($status < 200 || $status >= 300) {
            throw self::apiError($status, $isObject && is_array($decoded) ? $decoded : null, $decoded);
        }
        if (!$isObject || !is_array($decoded)) {
            throw new SMEPayException('invalid_response', "SMEPay reply for {$path} was not a JSON object", $status, $decoded);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Build an exception from an API error reply: {error, message} or {error, errors}.
     *
     * @param array<mixed>|null $body
     */
    private static function apiError(int $status, ?array $body, mixed $raw): SMEPayException
    {
        $error = $body['error'] ?? null;
        $code = is_string($error) && $error !== '' ? $error : 'api_error';

        $message = "SMEPay API error (HTTP {$status})";
        $text = $body['message'] ?? null;
        $errors = $body['errors'] ?? null;
        if (is_string($text) && $text !== '') {
            $message = $text;
        } elseif (is_string($errors) && $errors !== '') {
            $message = $errors;
        } elseif ($errors !== null) {
            $message = (string) json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return new SMEPayException($code, $message, $status, $raw);
    }
}
