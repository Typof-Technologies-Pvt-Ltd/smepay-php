<?php

declare(strict_types=1);

namespace SMEPay\Internal;

use SMEPay\SMEPayException;

/**
 * Logs in with client_id/client_secret and caches the access token for this client object.
 *
 * @internal not part of the public API; may change without notice
 */
final class TokenManager
{
    private const AUTH_PATH = '/api/external/auth';

    /** Fetch a new token when less than this many seconds of its lifetime are left. */
    private const REFRESH_MARGIN_SECONDS = 60.0;

    private ?string $token = null;

    /** Monotonic time (seconds) at which the cached token expires. */
    private float $expiresAt = 0.0;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
    ) {
    }

    /**
     * Return the cached token, or log in if there is none or it is about to expire.
     *
     * @throws SMEPayException
     */
    public function getToken(): string
    {
        if ($this->token !== null && self::now() < $this->expiresAt - self::REFRESH_MARGIN_SECONDS) {
            return $this->token;
        }

        // Measured before the call so network time counts against the token's lifetime.
        $startedAt = self::now();
        $reply = HttpClient::post(
            $this->baseUrl,
            self::AUTH_PATH,
            ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret],
        );
        $token = $reply['access_token'] ?? null;
        $expiresIn = $reply['expires_in'] ?? null;
        if (!is_string($token) || $token === '' || !(is_int($expiresIn) || is_float($expiresIn)) || $expiresIn <= 0) {
            throw new SMEPayException('invalid_response', 'SMEPay auth reply did not contain an access token', 200, $reply);
        }

        $this->token = $token;
        $this->expiresAt = $startedAt + (float) $expiresIn;

        return $token;
    }

    /** Drop the cached token if it is the one the server just rejected. */
    public function invalidate(#[\SensitiveParameter] string $token): void
    {
        if ($this->token === $token) {
            $this->token = null;
            $this->expiresAt = 0.0;
        }
    }

    /** @return array<string, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    private static function now(): float
    {
        return hrtime(true) / 1e9;
    }
}
