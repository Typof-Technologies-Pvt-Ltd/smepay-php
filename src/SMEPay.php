<?php

declare(strict_types=1);

namespace SMEPay;

use SMEPay\Internal\ApiClient;

/**
 * SMEPay External API client. Create one and reuse it.
 *
 *     $client = new \SMEPay\SMEPay([
 *         'client_id' => getenv('SMEPAY_CLIENT_ID'),
 *         'client_secret' => getenv('SMEPAY_CLIENT_SECRET'),
 *         'environment' => 'test', // "test" (default) or "live"
 *     ]);
 *
 * "environment" is "test" (default, staging.smepay.in) or "live" (extranet.smepay.in) and must match
 * your business's mode on SMEPay. Bad config throws SMEPayException "invalid_config".
 */
final class SMEPay
{
    private const BASE_URLS = [
        'test' => 'https://staging.smepay.in',
        'live' => 'https://extranet.smepay.in',
    ];

    private const OPTIONS = ['client_id', 'client_secret', 'environment'];

    /** @var 'test'|'live' */
    private readonly string $environment;

    /** @phpstan-ignore property.onlyWritten */
    private readonly ApiClient $api;

    /**
     * @param array<string, mixed> $config "client_id", "client_secret", optional "environment" ("test" | "live")
     *
     * @throws SMEPayException invalid_config
     */
    public function __construct(#[\SensitiveParameter] array $config)
    {
        foreach (array_keys($config) as $key) {
            if (!in_array($key, self::OPTIONS, true)) {
                throw new SMEPayException('invalid_config', "Unknown option \"{$key}\"; expected client_id, client_secret, environment");
            }
        }

        // Also catches false/null from an unset environment variable.
        $clientId = $config['client_id'] ?? null;
        if (!is_string($clientId) || trim($clientId) === '') {
            throw new SMEPayException('invalid_config', 'client_id is required');
        }
        $clientSecret = $config['client_secret'] ?? null;
        if (!is_string($clientSecret) || trim($clientSecret) === '') {
            throw new SMEPayException('invalid_config', 'client_secret is required');
        }
        $environment = $config['environment'] ?? 'test';
        if ($environment !== 'test' && $environment !== 'live') {
            throw new SMEPayException('invalid_config', 'environment must be "test" or "live", got ' . var_export($environment, true));
        }

        $this->environment = $environment;
        $this->api = new ApiClient(self::BASE_URLS[$environment], trim($clientId), trim($clientSecret));
    }

    /**
     * The SMEPay environment this client uses: "test" or "live".
     *
     * @return 'test'|'live'
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /** @return array{environment: 'test'|'live'} */
    public function __debugInfo(): array
    {
        return ['environment' => $this->environment];
    }
}
