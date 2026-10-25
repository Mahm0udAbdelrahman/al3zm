<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Quoted = 'quoted';
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Released = 'released';

    public function isBookable(): bool
    {
        // A released hold can still be booked if the payment lands late.
        return in_array($this, [self::Quoted, self::Held, self::Released], true);
    }
}
