<?php

namespace App\Services\Api\Travel;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\ProviderRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Books every item of a paid order with its provider. What could not be
 * booked is refunded; the order ends confirmed, partially confirmed or
 * failed.
 */
class FulfillmentService
{
    public function __construct(public ProviderRegistry $providers, public PaymentService $payments) {}

    public function fulfill(int $orderId): void
    {
        // Claim the order so a retried job never books twice.
        $order = DB::transaction(function () use ($orderId) {
            $order = Order::whereKey($orderId)->lockForUpdate()->first();
            if (! $order || $order->status !== OrderStatus::Paid) {
                return null;
            }
            $order->update(['status' => OrderStatus::Fulfilling]);

            return $order;
        });
        if (! $order) {
            return;
        }

        $failed = 0.0;
        $booked = 0;
        foreach ($order->items as $item) {
            if (! $item->status->isBookable()) {
                continue;
            }

            try {
                $result = $this->providers->get($item->type, $item->provider)->book($item);
            } catch (Throwable $e) {
                report($e);
                $result = BookingResult::failed($e->getMessage());
            }

            if ($result->succeeded()) {
                $booked++;
                $item->update([
                    'status' => OrderItemStatus::Confirmed,
                    'provider_reference' => $result->reference ?? $item->provider_reference,
                    'provider_data' => array_merge($item->provider_data ?? [], $result->data),
                    'booked_at' => now(),
                    'error' => null,
                ]);
            } else {
                $failed += $item->amount;
                $item->update(['status' => OrderItemStatus::Failed, 'error' => $result->message]);
            }
        }

        $status = match (true) {
            $failed == 0.0 => OrderStatus::Confirmed,
            $booked === 0 => OrderStatus::Failed,
            default => OrderStatus::PartiallyConfirmed,
        };
        $order->update(['status' => $status, 'confirmed_at' => $booked ? now() : null]);

        if ($failed > 0) {
            $this->payments->refund($order, $failed);
        }
    }
}
