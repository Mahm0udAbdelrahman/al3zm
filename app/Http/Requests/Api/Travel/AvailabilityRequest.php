<?php

namespace App\Http\Requests\Api\Travel;

class AvailabilityRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'from' => 'required|date|after_or_equal:today',
            'to' => 'nullable|date|after_or_equal:from',
        ];
    }
}
