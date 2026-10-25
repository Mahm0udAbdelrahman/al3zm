<?php

namespace App\Http\Resources\Travel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'provider' => $this->provider,
            'offer_id' => $this->offer_id,
            'status' => $this->status->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->provider_reference,
            'ticket_url' => $this->data('ticket_url'),
            'travelers' => $this->travelers,
            'error' => $this->error,
            'held_until' => $this->held_until?->toIso8601String(),
            'booked_at' => $this->booked_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
