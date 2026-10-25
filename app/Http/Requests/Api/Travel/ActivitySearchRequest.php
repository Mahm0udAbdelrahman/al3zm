<?php

namespace App\Http\Requests\Api\Travel;

class ActivitySearchRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'city' => 'required_without_all:q,latitude|nullable|string|max:60',
            'q' => 'nullable|string|max:100',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'date' => 'nullable|date|after_or_equal:today',
        ] + $this->listRules();
    }
}
