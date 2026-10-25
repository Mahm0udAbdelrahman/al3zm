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
use RuntimeException;

/**
 * Thawani Pay (Oman, OMR): checkout session → hosted page → session status.
 * Amounts are in baisa. https://thawani-technologies.stoplight.io
 */
class ThawaniGateway extends Gateway
{
    public function key(): string
    {
        return 'thawani';
    }

    public function enabled(): bool
    {
        return $this->config('enabled') && filled($this->config('secret_key')) && filled($this->config('publishable_key'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withHeaders(['thawani-api-key' => $this->config('secret_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $webhookUrl): Checkout
    {
        $session = $this->ensureOk($this->http()->post('/checkout/session', [
            'client_reference_id' => (string) $payment->id,
            'mode' => 'payment',
            'products' => [[
                'name' => mb_substr(__('Booking').' '.$payment->order->reference, 0, 40),
                'quantity' => 1,
                'unit_amount' => Currency::toMinor($payment->amount, 'OMR'),
            ]],
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'cancelled=1',
            'metadata' => ['payment_id' => (string) $payment->id, 'order' => $payment->order->reference],
        ]))->json('data');

        return new Checkout(
            $session['session_id'],
            rtrim($this->config('checkout_url'), '/')."/{$session['session_id']}?key={$this->config('publishable_key')}",
            ['invoice' => $session['invoice'] ?? null],
        );
    }

    public function fetchStatus(Payment $payment): GatewayStatus
    {
        $session = $this->ensureOk($this->http()->get("/checkout/session/{$payment->gateway_reference}"))->json('data');

        $status = match ($session['payment_status'] ?? null) {
            'paid' => PaymentStatus::Paid,
            'cancelled', 'failed' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        $paymentId = null;
        if ($status === PaymentStatus::Paid && ! empty($session['invoice'])) {
            $paymentId = $this->http()->get('/payments', ['checkout_invoice' => $session['invoice'], 'limit' => 1, 'skip' => 0])
                ->json('data.0.payment_id');
        }

        return new GatewayStatus($status, $paymentId, ['invoice' => $session['invoice'] ?? null]);
    }

    public function refund(Payment $payment, float $amount): void
    {
        if (round($amount, 3) < round($payment->amount, 3)) {
            throw new RuntimeException('thawani: only full refunds are supported, refund the difference manually.');
        }

        $this->ensureOk($this->http()->post('/refunds', [
            'payment_id' => $payment->gateway_payment_id,
            'reason' => 'Booking could not be completed',
            'metadata' => ['payment_id' => (string) $payment->id],
        ]));
    }

    public function referenceFromCallback(Request $request): ?string
    {
        $secret = $this->config('webhook_secret');
        if ($secret) {
            $expected = hash_hmac('sha256', $request->getContent().'-'.$request->header('thawani-timestamp'), $secret);
            if (! hash_equals($expected, (string) $request->header('thawani-signature'))) {
                return null;
            }
        }

        return $request->input('data.session_id');
    }
}
