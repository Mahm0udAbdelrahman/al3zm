<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Services\Payments\Data\Checkout;
use App\Services\Payments\Data\GatewayStatus;
use Illuminate\Http\Request;

/**
 * A hosted-checkout payment gateway: the app opens `redirect_url`, the user
 * pays on the gateway's page, and we learn the result from the return
 * redirect and/or the webhook — always confirmed by asking the gateway
 * (fetchStatus), never by trusting the callback alone.
 */
interface PaymentGateway
{
    public function key(): string;

    public function enabled(): bool;

    /** Currencies it can charge, upper case; empty = any. */
    public function currencies(): array;

    public function createCheckout(Payment $payment, string $returnUrl, string $webhookUrl): Checkout;

    public function fetchStatus(Payment $payment): GatewayStatus;

    /** Refund part or all of a paid payment, in the payment's currency. */
    public function refund(Payment $payment, float $amount): void;

    /**
     * The gateway reference a callback (return redirect or webhook) is about,
     * after verifying its signature where the gateway signs it; null if the
     * request is not genuine or not about a payment.
     */
    public function referenceFromCallback(Request $request): ?string;
}
