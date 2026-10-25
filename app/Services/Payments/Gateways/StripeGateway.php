<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\Data\Checkout;
use App\Services\Payments\Data\GatewayStatus;
use App\Services\Travel\Support\Currency;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (international cards, Apple Pay / Google Pay).
 * https://docs.stripe.com/api/checkout/sessions
 */
class StripeGateway extends Gateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function enabled(): bool
    {
        return $this->config('enabled') && filled($this->config('secret_key'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withToken($this->config('secret_key'))
            ->asForm()
            ->acceptJson()
            ->timeout(30);
    }

    /** Stripe takes three-decimal currencies in multiples of 10. */
    private function minor(float $amount, string $currency): int
    {
        $minor = Currency::toMinor($amount, $currency);

        return Currency::decimals($currency) === 3 ? (int) (round($minor / 10) * 10) : $minor;
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $webhookUrl): Checkout
    {
        $session = $this->ensureOk($this->http()->post('/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => (string) $payment->id,
            'customer_email' => $payment->order->contact_email ?? $payment->user->email,
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'cancelled=1',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $this->minor($payment->amount, $payment->currency),
                    'product_data' => ['name' => __('Booking').' '.$payment->order->reference],
                ],
            ]],
            'metadata' => ['payment_id' => (string) $payment->id],
            'payment_intent_data' => ['metadata' => ['payment_id' => (string) $payment->id]],
        ]))->json();

        return new Checkout($session['id'], $session['url']);
    }

    public function fetchStatus(Payment $payment): GatewayStatus
    {
        $session = $this->ensureOk($this->http()->get("/checkout/sessions/{$payment->gateway_reference}"))->json();

        $status = match (true) {
            ($session['payment_status'] ?? null) === 'paid' => PaymentStatus::Paid,
            ($session['status'] ?? null) === 'expired' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new GatewayStatus($status, $session['payment_intent'] ?? null);
    }

    public function refund(Payment $payment, float $amount): void
    {
        $this->ensureOk($this->http()->post('/refunds', [
            'payment_intent' => $payment->gateway_payment_id,
            'amount' => $this->minor($amount, $payment->currency),
            'metadata' => ['payment_id' => (string) $payment->id],
        ]));
    }

    public function referenceFromCallback(Request $request): ?string
    {
        $secret = $this->config('webhook_secret');
        if (! $secret) {
            return null;
        }

        $parts = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, null);
            $parts[$k][] = $v;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $valid = abs(time() - $timestamp) <= 300
            && collect($parts['v1'] ?? [])->contains(fn ($sig) => hash_equals($expected, (string) $sig));

        if (! $valid || ! str_starts_with((string) $request->input('type'), 'checkout.session.')) {
            return null;
        }

        return $request->input('data.object.id');
    }
}
