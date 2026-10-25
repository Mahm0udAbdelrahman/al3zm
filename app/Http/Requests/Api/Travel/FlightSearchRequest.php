<?php

namespace App\Http\Requests\Api\Travel;

class FlightSearchRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'trip_type' => 'nullable|in:oneWay,roundTrip,multiCity',
            'legs' => 'required|array|min:1|max:6',
            'legs.*.from' => 'required|string|size:3',
            'legs.*.to' => 'required|string|size:3|different:legs.*.from',
            'legs.*.date' => 'required|date|after_or_equal:today',
            'return_date' => 'nullable|date|after_or_equal:legs.0.date',
            'adults' => 'required|integer|min:1|max:9',
            'children' => 'nullable|integer|min:0|max:8',
            'infants' => 'nullable|integer|min:0|lte:adults',
            'cabin' => 'nullable|in:economy,premium,business,first',
            'max_connections' => 'nullable|integer|min:0|max:2',
        ] + $this->listRules();
    }
}
