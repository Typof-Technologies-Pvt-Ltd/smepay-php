<?php

/**
 * Full flow: create → initiate → getStatus.
 *
 * Credentials come from environment variables (keep them in a .env file, never commit it):
 *
 *     SMEPAY_CLIENT_ID=...
 *     SMEPAY_CLIENT_SECRET=...
 *     SMEPAY_ENVIRONMENT=test          # optional: "test" (default) or "live"
 *     SMEPAY_CALLBACK_URL=https://...  # optional: where the result webhook is sent
 *
 * Run from the repo root (after `composer install`):
 *
 *     set -a; source .env; set +a
 *     php examples/full_flow.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SMEPay\SMEPay;
use SMEPay\SMEPayException;

$callbackUrl = getenv('SMEPAY_CALLBACK_URL');

try {
    $client = new SMEPay([
        'client_id' => getenv('SMEPAY_CLIENT_ID'),
        'client_secret' => getenv('SMEPAY_CLIENT_SECRET'),
        'environment' => getenv('SMEPAY_ENVIRONMENT') === 'live' ? 'live' : 'test',
    ]);
    echo 'Environment: ', $client->getEnvironment(), "\n";

    // 1. Create the order. order_id is your own reference and must be unique.
    $order = $client->payment->create([
        'amount' => '1.00',
        'order_id' => 'DEMO-' . (int) (microtime(true) * 1000),
        'callback_url' => is_string($callbackUrl) && $callbackUrl !== '' ? $callbackUrl : 'https://example.com/smepay/callback',
        'customer_details' => ['name' => 'Demo Customer', 'mobile' => '9999999999'],
    ]);
    echo "\n1. create\n";
    echo '   order_id (SMEPay): ', $order['order_id'], "\n";
    echo '   ref_id (yours):    ', $order['ref_id'], "\n";
    echo '   order_slug:        ', $order['order_slug'], "\n";
    echo '   payment_status:    ', $order['payment_status'], "\n";
    echo '   payment_url:       ', $order['payment_url'], "\n";

    // 2. Start the payment: show the QR code or UPI app links to the customer.
    $payment = $client->payment->initiate(['slug' => $order['order_slug']]);
    $apps = array_keys($payment['intents']);
    sort($apps);
    echo "\n2. initiate\n";
    echo '   payment_status:    ', $payment['payment_status'], "\n";
    echo '   payment_link:      ', $payment['payment_link'], "\n";
    echo '   qr_code:           base64, ', strlen($payment['qr_code']), " chars\n";
    echo '   intents:           ', $apps === [] ? '(none)' : implode(', ', $apps), "\n";
    echo '   expires_at:        ', date('c', $payment['expires_at']), "\n";

    // 3. Check the status by your reference (or by SMEPay's order_id).
    $status = $client->payment->getStatus(['external_reference_id' => $order['ref_id']]);
    echo "\n3. getStatus\n";
    echo '   payment_status:    ', $status['payment_status'], "\n";
    echo '   amount:            ', $status['amount'], "\n";
    echo '   processed_at:      ', $status['processed_at'] !== null ? date('c', $status['processed_at']) : '(not yet)', "\n";
    echo '   utr:               ', $status['utr'] ?? '(none yet)', "\n";
} catch (SMEPayException $e) {
    echo "\nSMEPayException [", $e->getErrorCode(), '] (HTTP ', $e->getStatusCode() ?? '-', '): ', $e->getMessage(), "\n";
    exit(1);
}
