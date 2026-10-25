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
 * Tap Payments (GCC + international cards, Apple Pay, KNET, mada, Benefit…):
 * charge → hosted page (`transaction.url`) → charge status.
 * https://developers.tap.company/reference/create-a-charge
 */
class TapGateway extends Gateway
{
    public function key(): string
    {
        return 'tap';
    }

    public function enabled(): bool
    {
        return $this->config('enabled') && filled($this->config('secret_key'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withToken($this->config('secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $webhookUrl): Checkout
    {
        $user = $payment->user;
        [$first, $last] = array_pad(explode(' ', trim($user->name), 2), 2, '-');

        $charge = $this->ensureOk($this->http()->post('/charges', [
            'amount' => (float) Currency::format($payment->amount, $payment->currency),
            'currency' => $payment->currency,
            'threeDSecure' => true,
            'save_card' => false,
            'customer_initiated' => true,
            'description' => __('Booking').' '.$payment->order->reference,
            'metadata' => ['payment_id' => (string) $payment->id],
            'reference' => ['transaction' => 'pay_'.$payment->id, 'order' => $payment->order->reference],
            'receipt' => ['email' => true, 'sms' => false],
            'customer' => array_filter([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $payment->order->contact_email ?? $user->email,
            ]),
            'source' => ['id' => $this->config('source')],
            'post' => ['url' => $webhookUrl],
            'redirect' => ['url' => $returnUrl],
        ]))->json();

        return new Checkout($charge['id'], data_get($charge, 'transaction.url'));
    }

    public function fetchStatus(Payment $payment): GatewayStatus
    {
        $charge = $this->ensureOk($this->http()->get("/charges/{$payment->gateway_reference}"))->json();

        $status = match ($charge['status'] ?? null) {
            'CAPTURED' => PaymentStatus::Paid,
            'FAILED', 'DECLINED', 'CANCELLED', 'ABANDONED', 'TIMEDOUT', 'RESTRICTED', 'VOID', 'UNKNOWN' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new GatewayStatus($status, $charge['id'], ['tap_status' => $charge['status'] ?? null]);
    }

    public function refund(Payment $payment, float $amount): void
    {
        $this->ensureOk($this->http()->post('/refunds', [
            'charge_id' => $payment->gateway_reference,
            'amount' => (float) Currency::format($amount, $payment->currency),
            'currency' => $payment->currency,
            'reason' => 'requested_by_customer',
            'reference' => ['merchant' => 'refund_'.$payment->id.'_'.time()],
        ]));
    }

    public function referenceFromCallback(Request $request): ?string
    {
        $id = $request->input('id');
        if (! $id) {
            return null;
        }

        // Tap signs the webhook with the secret key over these fields.
        $hash = $request->header('hashstring');
        if ($hash) {
            $currency = (string) $request->input('currency');
            $string = 'x_id'.$id
                .'x_amount'.Currency::format((float) $request->input('amount'), $currency)
                .'x_currency'.$currency
                .'x_gateway_reference'.$request->input('reference.gateway')
                .'x_payment_reference'.$request->input('reference.payment')
                .'x_status'.$request->input('status')
                .'x_created'.$request->input('transaction.created');

            if (! hash_equals(hash_hmac('sha256', $string, (string) $this->config('secret_key')), $hash)) {
                return null;
            }
        }

        return $id;
    }
}
