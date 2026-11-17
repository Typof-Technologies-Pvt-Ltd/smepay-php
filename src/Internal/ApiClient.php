<?php

declare(strict_types=1);

namespace SMEPay\Internal;

use SMEPay\SMEPayException;

/**
 * Authenticated POSTs with client_id added to every body.
 *
 * @internal not part of the public API; may change without notice
 */
final class ApiClient
{
    /** 401 codes meaning the token itself was rejected; a fresh login may fix these. */
    private const TOKEN_ERROR_CODES = ['invalid_token', 'invalid_external_token', 'invalid_token_type'];

    private readonly TokenManager $auth;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        #[\SensitiveParameter] string $clientSecret,
    ) {
        $this->auth = new TokenManager($baseUrl, $clientId, $clientSecret);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws SMEPayException
     */
    public function post(string $path, array $params): array
    {
        $body = [...$params, 'client_id' => $this->clientId];
        $token = $this->auth->getToken();
        try {
            return HttpClient::post($this->baseUrl, $path, $body, $token);
        } catch (SMEPayException $e) {
            if ($e->getStatusCode() !== 401 || !in_array($e->getErrorCode(), self::TOKEN_ERROR_CODES, true)) {
                throw $e;
            }
        }

        // Token rejected (e.g. server session lost): log in again once and repeat the call once.
        $this->auth->invalidate($token);

        return HttpClient::post($this->baseUrl, $path, $body, $this->auth->getToken());
    }

    /** @return array<string, never> */
    public function __debugInfo(): array
    {
        return [];
    }
}
