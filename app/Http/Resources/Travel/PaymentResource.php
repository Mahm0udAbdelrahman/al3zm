<?php

namespace App\Http\Resources\Travel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order?->reference,
            'gateway' => $this->gateway,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'refunded_amount' => $this->refunded_amount,
            'redirect_url' => $this->when($this->status->value === 'pending', $this->redirect_url),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
