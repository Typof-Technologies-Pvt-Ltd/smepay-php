<?php

declare(strict_types=1);

namespace SMEPay;

use SMEPay\Internal\ApiClient;

/**
 * Payment calls: create → initiate → getStatus. Use it as $client->payment.
 *
 * Arrays in, arrays out; keys are exactly the API's.
 *
 * @phpstan-type PaymentStatus 'CREATED'|'INITIATED'|'PENDING'|'SUCCESS'|'FAILED'|'EXPIRED'|'SETTLED'|'REFUNDED'|'REFUND_PENDING'|'TEST_SUCCESS'|'TEST_FAILED'|'TEST_PENDING'
 * @phpstan-type CustomerDetails array{name?: string, email?: string, mobile?: string}
 * @phpstan-type CreateParams array{amount: string, order_id: string, callback_url: string, customer_details: CustomerDetails}
 * @phpstan-type CreateResponse array{status: bool, order_slug: string, order_id: string, amount: int|float, payment_status: PaymentStatus, payment_url: string, ref_id: string, customer_details: array<string, string>, message: string, callback_url?: string}
 * @phpstan-type InitiateParams array{slug: string}
 * @phpstan-type InitiateResponse array{status: bool, order_id: string, external_reference_id: string, payment_link: string, transaction_id: string, qr_code: string, payment_status: PaymentStatus, expires_at: int, intents: array<string, string>, message: string}
 * @phpstan-type GetStatusParams array{order_id?: string, external_reference_id?: string, callback_url?: string}
 * @phpstan-type GetStatusResponse array{status: bool, external_reference_id: string, order_id: string, payment_status: PaymentStatus, amount: int|float, created_at: int, processed_at: int|null, utr?: string}
 */
final class Payment
{
    /** @internal created by SMEPay; use $client->payment */
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Create a payment order. Next step: initiate(['slug' => $reply['order_slug']]).
     *
     * - amount: in rupees, as a string, e.g. "100.50"
     * - order_id: your own reference; must be unique for your business
     * - callback_url: URL that receives the payment result webhook
     * - customer_details: name, email, mobile; email or mobile required
     *
     * @param CreateParams $params
     *
     * @return CreateResponse
     *
     * @throws SMEPayException
     */
    public function create(array $params): array
    {
        /** @var CreateResponse */
        return $this->api->post('/api/external/order/create', $params);
    }

    /**
     * Start the payment for an order; returns the UPI link, QR code (base64 PNG) and app intents.
     *
     * - slug: order_slug from create
     *
     * @param InitiateParams $params
     *
     * @return InitiateResponse
     *
     * @throws SMEPayException
     */
    public function initiate(array $params): array
    {
        /** @var InitiateResponse */
        return $this->api->post('/api/external/order/initiate', $params);
    }

    /**
     * Fetch the current payment status of an order.
     *
     * Pass order_id (SMEPay's) or external_reference_id (yours); the latter is used first if both are given.
     * callback_url is saved only if the order has none yet.
     *
     * @param GetStatusParams $params
     *
     * @return GetStatusResponse
     *
     * @throws SMEPayException
     */
    public function getStatus(array $params): array
    {
        /** @var GetStatusResponse */
        return $this->api->post('/api/external/order/status', $params);
    }

    /** @return array<string, never> */
    public function __debugInfo(): array
    {
        return [];
    }
}
