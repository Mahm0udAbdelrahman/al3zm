<?php

namespace App\Services\Api\Travel;

use App\Enums\ItemType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Travel\Contracts\HoldsInventory;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\Support\CurrencyConverter;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\OfferStore;
use App\Services\Travel\Support\Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * An order is anything the user picked — flights, stays, activities, from
 * any provider — priced again with each provider, held where possible, and
 * paid in one payment.
 */
class OrderService
{
    public function __construct(
        public ProviderRegistry $providers,
        public OfferStore $offers,
        public Pricing $pricing,
        public CurrencyConverter $converter,
        public PaymentService $payments,
    ) {}

    public function create(User $user, array $data): Order
    {
        $quoted = [];
        $changes = [];
        foreach ($data['items'] as $index => $input) {
            $type = ItemType::from($input['type']);
            $provider = $this->providers->get($type, OfferRef::parse($input['offer_id'])->provider);
            $entry = $this->offers->get($input['offer_id']);
            $quote = $provider->quote($entry, $input);

            $amount = $this->pricing->sell($quote->amount, $quote->currency, $type);
            $expected = $entry['offer']['total_amount'] ?? null;
            if ($expected) {
                $withoutExtras = $this->pricing->sell($quote->amount - $quote->extras, $quote->currency, $type);
                $change = abs($withoutExtras - $expected) / $expected * 100;
                if ($change > config('travel.price_change_tolerance') && empty($data['accept_price_change'])) {
                    $changes[] = ['index' => $index, 'offer_id' => $input['offer_id'], 'old_amount' => $expected, 'new_amount' => $withoutExtras];
                }
            }

            $quoted[] = compact('type', 'provider', 'input', 'quote', 'amount');
        }

        if ($changes) {
            throw new TravelException(__('The price has changed. Please review and confirm the new price.'), 409, [
                'currency' => $this->pricing->currency(),
                'items' => $changes,
            ]);
        }

        $order = DB::transaction(function () use ($user, $data, $quoted) {
            $order = Order::create([
                'reference' => Order::newReference(),
                'user_id' => $user->id,
                'status' => OrderStatus::PendingPayment,
                'currency' => $this->pricing->currency(),
                'total_amount' => array_sum(array_column($quoted, 'amount')),
                'contact_email' => $data['contact']['email'] ?? $user->email,
                'contact_phone' => $data['contact']['phone'] ?? $user->phone,
                'expires_at' => now()->addMinutes(config('travel.order_ttl')),
            ]);

            foreach ($quoted as $q) {
                $order->items()->create([
                    'type' => $q['type'],
                    'provider' => $q['provider']->key(),
                    'offer_id' => $q['input']['offer_id'],
                    'status' => OrderItemStatus::Quoted,
                    'title' => $q['quote']->title,
                    'summary' => $q['quote']->summary,
                    'amount' => $q['amount'],
                    'currency' => $this->pricing->currency(),
                    'provider_amount' => $q['quote']->amount,
                    'provider_currency' => $q['quote']->currency,
                    'provider_data' => $q['quote']->data,
                    'travelers' => $this->travelers($q['type'], $q['input']),
                ]);
            }

            return $order;
        });

        $this->hold($order);

        return $order->load('items');
    }

    /** What each provider needs about the people travelling. */
    private function travelers(ItemType $type, array $input): array
    {
        return match ($type) {
            ItemType::Flight => $input['passengers'],
            ItemType::Stay => [
                'guests' => $input['guests'],
                'email' => $input['email'],
                'phone_number' => $input['phone_number'],
            ],
            ItemType::Activity => $input['traveler'],
        };
    }

    /** Keep seats and slots while the user pays; a failed hold is booked later. */
    private function hold(Order $order): void
    {
        if (! config('travel.hold_when_possible')) {
            return;
        }

        foreach ($order->items as $item) {
            $provider = $this->providers->get($item->type, $item->provider);
            if (! $provider instanceof HoldsInventory || ! $provider->canHold($item)) {
                continue;
            }
            try {
                $result = $provider->hold($item);
                $item->update([
                    'status' => OrderItemStatus::Held,
                    'provider_reference' => $result->reference,
                    'provider_data' => array_merge($item->provider_data ?? [], $result->data),
                    'held_until' => $result->heldUntil,
                ]);
            } catch (Throwable $e) {
                Log::warning('Hold failed, will book after payment', ['item' => $item->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Give back every hold of an order that will not be paid. */
    public function release(Order $order, OrderStatus $status): void
    {
        foreach ($order->items as $item) {
            if ($item->status === OrderItemStatus::Held) {
                try {
                    $provider = $this->providers->get($item->type, $item->provider);
                    if ($provider instanceof HoldsInventory) {
                        $provider->release($item);
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
            if ($item->status->isBookable()) {
                $item->update(['status' => OrderItemStatus::Released]);
            }
        }

        $order->update(['status' => $status, 'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null]);
    }

    public function cancel(Order $order): Order
    {
        if ($order->status === OrderStatus::PendingPayment || $order->status === OrderStatus::Expired) {
            $this->release($order, OrderStatus::Cancelled);

            return $order->fresh('items', 'payments');
        }

        if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::PartiallyConfirmed], true)) {
            throw new TravelException(__('This order cannot be cancelled now.'), 409);
        }

        $refund = 0.0;
        $failures = [];
        foreach ($order->items->where('status', OrderItemStatus::Confirmed) as $item) {
            try {
                $result = $this->providers->get($item->type, $item->provider)->cancel($item);
            } catch (Throwable $e) {
                report($e);
                $failures[] = $item->title;

                continue;
            }
            if (! $result->cancelled) {
                $failures[] = $item->title;

                continue;
            }

            // Pass on what the provider refunds, up to what the user paid for it.
            $back = $result->refundAmount !== null
                ? min($item->amount, $this->converter->convert($result->refundAmount, $result->currency ?? $item->provider_currency))
                : 0.0;
            $refund += $back;
            $item->update([
                'status' => OrderItemStatus::Cancelled,
                'cancelled_at' => now(),
                'provider_data' => array_merge($item->provider_data ?? [], ['cancellation' => $result->data, 'refund' => $back]),
            ]);
        }

        if ($refund > 0) {
            $this->payments->refund($order, $refund);
        }

        $remaining = $order->items()->where('status', OrderItemStatus::Confirmed)->exists();
        $order->update([
            'status' => $remaining ? OrderStatus::PartiallyConfirmed : OrderStatus::Cancelled,
            'cancelled_at' => $remaining ? null : now(),
            'meta' => array_merge($order->meta ?? [], $failures ? ['cancel_failed' => $failures] : []),
        ]);

        return $order->fresh('items', 'payments');
    }

    /** Unpaid orders past their time: release holds. */
    public function expireStale(): int
    {
        $count = 0;
        Order::with('items')
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<', now())
            ->each(function (Order $order) use (&$count) {
                $this->release($order, OrderStatus::Expired);
                $count++;
            });

        return $count;
    }
}
