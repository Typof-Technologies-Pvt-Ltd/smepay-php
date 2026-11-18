# smepay-php

Official PHP SDK for the SMEPay External API. Accept UPI payments in three calls; login, tokens and errors are handled for you.

PHP 8.1+ · zero dependencies (ext-curl, ext-json) · type hints for PHPStan / PhpStorm.

```bash
composer require smepay/smepay-php
```

## Usage

```php
use SMEPay\SMEPay;

$client = new SMEPay([
    'client_id' => getenv('SMEPAY_CLIENT_ID'), // from the SMEPay dashboard
    'client_secret' => getenv('SMEPAY_CLIENT_SECRET'),
    'environment' => 'test', // "test" (default) or "live"
]);

// 1. Create an order
$order = $client->payment->create([
    'amount' => '100.50', // string, in rupees
    'order_id' => 'ORD-1001', // your reference, unique per order
    'callback_url' => 'https://yoursite.com/smepay/callback',
    'customer_details' => ['name' => 'Asha', 'mobile' => '9876543210'], // email or mobile required
]);

// 2. Start the payment: show $payment['qr_code'] (base64 PNG) or $payment['intents'] (UPI app links)
$payment = $client->payment->initiate(['slug' => $order['order_slug']]);

// 3. Check the status (or use 'order_id' => $order['order_id'])
$status = $client->payment->getStatus(['external_reference_id' => 'ORD-1001']);
echo $status['payment_status'], ' ', $status['utr'] ?? ''; // "SUCCESS 123456789012"
```

Create one client and reuse it within a request. Array keys match the API exactly; replies are plain arrays. Each request waits at most 60 seconds.

| Method | Returns |
|---|---|
| `payment->create` | `order_slug`, `order_id` (SMEPay's), `ref_id` (yours), `payment_url`, `payment_status` |
| `payment->initiate` | `payment_link`, `qr_code`, `intents`, `expires_at`, `payment_status` |
| `payment->getStatus` | `payment_status`, `amount`, `created_at`, `processed_at` (`null` until processed), `utr` |

`initiate` can be called again if a payment failed or expired. Alternatively, skip `initiate` and redirect the customer to `payment_url` (the hosted page); use one or the other per order.

**Statuses:** `CREATED` → `INITIATED` / `PENDING` → `SUCCESS` or `FAILED` / `EXPIRED`; also `SETTLED`, `REFUNDED`, `REFUND_PENDING`. Test-mode businesses get `TEST_SUCCESS`, `TEST_FAILED`, `TEST_PENDING`.

## Errors

Every failure throws `SMEPay\SMEPayException` with `getErrorCode()`, `getMessage()`, `getStatusCode()` (`null` if no reply) and `getRaw()` (API reply).

```php
use SMEPay\SMEPayException;

try {
    $client->payment->create([...]);
} catch (SMEPayException $e) {
    if ($e->getErrorCode() === 'duplicate_order_id') {
        // ...
    }
}
```

| Code | Meaning |
|---|---|
| `invalid_config` | Missing `client_id` / `client_secret`, bad `environment` or unknown option |
| `invalid_client_id`, `invalid_credentials` | Wrong credentials |
| `environment_mismatch` | `environment` doesn't match your business's mode |
| `ip_not_whitelisted` | Live: your server IP isn't whitelisted (ask SMEPay support) |
| `api_not_enabled` | External API not enabled for your business (contact SMEPay) |
| `insufficient_wallet_balance`, `wallet_validation_failed` | Wallet balance too low (live needs ≥ ₹10) |
| `invalid_request`, `validation_error` | Missing or invalid field |
| `amount_below_minimum`, `amount_exceeds_maximum` | Amount outside your allowed range |
| `duplicate_order_id` | `order_id` already used |
| `transaction_not_found` | No such order for your business |
| `payment_already_processed` | `initiate` on an order already paid / in progress |
| `network_error`, `timeout`, `invalid_response` | Couldn't reach SMEPay / no reply in 60s / unreadable reply |

Calls are never retried automatically, so a failed `create` is never sent twice.

## Test vs live

| | `test` | `live` |
|---|---|---|
| Server | `staging.smepay.in` | `extranet.smepay.in` |
| Money / fees | None | Real; fees from your SMEPay wallet |
| Requirements | — | Server IP whitelisted, wallet ≥ ₹10 |

## Webhooks

When a payment finishes, SMEPay POSTs JSON to your `callback_url`: `ref_id` (your order ID), `status`, `transaction_id`, `amount` (string, e.g. `"100.00"`), `created_at` / `processed_at` (ISO-8601) and `utr` (when available). Reply with any `2xx` quickly; other replies are retried, so handle duplicates. Confirm with `payment->getStatus` before marking an order paid.

## Security

Use the SDK only on your server. Keep `client_secret` in an environment variable, never in code. The SDK never logs your secret or token, and `var_dump` / `print_r` of the client don't show them.

## Example

[`examples/full_flow.php`](https://github.com/mahi-qwe/smepay-php/blob/main/examples/full_flow.php): put `SMEPAY_CLIENT_ID` / `SMEPAY_CLIENT_SECRET` in `.env`, run `composer install`, then `set -a; source .env; set +a; php examples/full_flow.php`.

## License

MIT
