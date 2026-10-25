<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'provider',
        'offer_id',
        'status',
        'title',
        'summary',
        'amount',
        'currency',
        'provider_amount',
        'provider_currency',
        'provider_reference',
        'provider_data',
        'travelers',
        'error',
        'held_until',
        'booked_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'status' => OrderItemStatus::class,
            'summary' => 'array',
            'provider_data' => 'array',
            'travelers' => 'array',
            'amount' => 'float',
            'provider_amount' => 'float',
            'held_until' => 'datetime',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return data_get($this->provider_data, $key, $default);
    }
}
