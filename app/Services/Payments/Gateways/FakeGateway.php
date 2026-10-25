<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\Data\Checkout;
use App\Services\Payments\Data\GatewayStatus;
use Illuminate\Http\Request;

/**
 * Local development and tests: the "checkout page" is our own return URL
 * with `simulate=paid` (or `failed`). Never enabled in production.
 */
class FakeGateway extends Gateway
{
    public function key(): string
    {
        return 'fake';
    }

    public function enabled(): bool
    {
        return $this->config('enabled') && ! app()->isProduction();
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $webhookUrl): Checkout
    {
        return new Checkout('fake_'.$payment->id, $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'simulate=paid');
    }

    public function fetchStatus(Payment $payment): GatewayStatus
    {
        $simulate = request()?->query('simulate') ?? ($payment->payload['simulate'] ?? null);

        return new GatewayStatus(match ($simulate) {
            'paid' => PaymentStatus::Paid,
            'failed' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        }, $payment->gateway_reference, ['simulate' => $simulate]);
    }

    public function refund(Payment $payment, float $amount): void {}

    public function referenceFromCallback(Request $request): ?string
    {
        // No webhooks: the return redirect is the only callback.
        return null;
    }
}
