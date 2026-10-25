<?php

namespace App\Http\Requests\Api\Travel;

use App\Services\Payments\PaymentGatewayManager;

class PayRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'gateway' => 'required|string|in:'.implode(',', array_keys(PaymentGatewayManager::GATEWAYS)),
        ];
    }
}
