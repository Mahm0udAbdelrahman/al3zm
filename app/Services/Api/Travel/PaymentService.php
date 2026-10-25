<?php

namespace App\Services\Api\Travel;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\FulfillOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\Support\Currency;
use App\Services\Travel\Support\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Takes the money for an order through the gateway the user picked, and
 * hands a paid order to fulfillment. A payment is only ever marked paid
 * after asking the gateway itself.
 */
class PaymentService
{
    public function __construct(public PaymentGatewayManager $gateways, public CurrencyConverter $converter) {}

    public function start(Order $order, string $gatewayKey): Payment
    {
        if ($order->status === OrderStatus::PendingPayment && $order->expires_at?->isPast()) {
            app(OrderService::class)->release($order, OrderStatus::Expired);
        }
        if (! $order->status->isPayable()) {
            throw new TravelException(__('This order can no longer be paid. Please search again.'), 409);
        }

        $gateway = $this->gateways->get($gatewayKey);

        // Charge in the order's currency when the gateway takes it.
        $currencies = $gateway->currencies();
        $currency = ! $currencies || in_array($order->currency, $currencies, true) ? $order->currency : $currencies[0];
        $amount = $currency === $order->currency
            ? $order->total_amount
            : $this->converter->convert($order->total_amount, $order->currency, $currency);

        $payment = $order->payments()->create([
            'user_id' => $order->user_id,
            'gateway' => $gateway->key(),
            'status' => PaymentStatus::Pending,
            'amount' => Currency::round($amount, $currency),
            'currency' => $currency,
        ]);

        $checkout = $gateway->createCheckout(
            $payment->load('order', 'user'),
            route('payments.return', $payment),
            route('payments.webhook', $gateway->key()),
        );

        $payment->update([
            'gateway_reference' => $checkout->reference,
            'redirect_url' => $checkout->redirectUrl,
            'payload' => $checkout->payload,
        ]);

        return $payment;
    }

    /** Ask the gateway how the payment went and act on it, once. */
    public function confirm(Payment $payment): Payment
    {
        if ($payment->status->isFinal()) {
            return $payment;
        }

        $result = $this->gateways->get($payment->gateway)->fetchStatus($payment);
        if ($result->status === PaymentStatus::Pending) {
            return $payment;
        }

        $refundCancelled = false;
        DB::transaction(function () use ($payment, $result, &$refundCancelled) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked->status->isFinal()) {
                return;
            }

            $locked->update([
                'status' => $result->status,
                'gateway_payment_id' => $result->paymentId ?? $locked->gateway_payment_id,
                'payload' => array_merge($locked->payload ?? [], $result->payload),
                'paid_at' => $result->status === PaymentStatus::Paid ? now() : null,
            ]);

            if ($result->status !== PaymentStatus::Paid) {
                return;
            }

            $order = Order::whereKey($locked->order_id)->lockForUpdate()->first();
            if ($order->status === OrderStatus::Cancelled) {
                $refundCancelled = true;

                return;
            }

            // An expired order still gets booked: its holds are gone, but the
            // providers may still have the seats.
            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            FulfillOrder::dispatch($order->id)->afterCommit();
        });

        $payment->refresh();
        if ($refundCancelled) {
            $this->refund($payment->order, $payment->amount, $payment);
        }

        return $payment;
    }

    public function webhook(string $gatewayKey, Request $request): ?Payment
    {
        $reference = $this->gateways->get($gatewayKey)->referenceFromCallback($request);
        if (! $reference) {
            return null;
        }

        $payment = Payment::where('gateway', $gatewayKey)->where('gateway_reference', $reference)->first();

        return $payment ? $this->confirm($payment) : null;
    }

    /**
     * Give money back, in the order's currency. A refund the gateway refuses
     * is recorded on the order for someone to do by hand.
     */
    public function refund(Order $order, float $amount, ?Payment $payment = null): void
    {
        $payment ??= $order->paidPayment();
        if (! $payment || $amount <= 0) {
            return;
        }

        $inPaymentCurrency = $payment->currency === $order->currency
            ? $amount
            : $this->converter->convert($amount, $order->currency, $payment->currency);
        $inPaymentCurrency = min($payment->refundable(), Currency::round($inPaymentCurrency, $payment->currency));
        if ($inPaymentCurrency <= 0) {
            return;
        }

        try {
            $this->gateways->get($payment->gateway)->refund($payment, $inPaymentCurrency);
        } catch (Throwable $e) {
            Log::error('Refund failed', ['payment' => $payment->id, 'amount' => $inPaymentCurrency, 'error' => $e->getMessage()]);
            $order->update(['meta' => array_merge($order->meta ?? [], [
                'refund_pending' => ($order->meta['refund_pending'] ?? 0) + $inPaymentCurrency,
                'refund_error' => $e->getMessage(),
            ])]);

            return;
        }

        $refunded = $payment->refunded_amount + $inPaymentCurrency;
        $payment->update([
            'refunded_amount' => $refunded,
            'status' => $refunded >= $payment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
        ]);
    }
}
