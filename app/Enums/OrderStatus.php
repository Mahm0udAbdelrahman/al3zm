<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Fulfilling = 'fulfilling';
    case Confirmed = 'confirmed';
    case PartiallyConfirmed = 'partially_confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isPayable(): bool
    {
        return $this === self::PendingPayment;
    }
}
