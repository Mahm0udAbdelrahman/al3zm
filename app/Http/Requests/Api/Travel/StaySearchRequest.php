<?php

namespace App\Http\Requests\Api\Travel;

class StaySearchRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'city' => 'required_without:latitude|nullable|string|max:60',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:50',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:16',
            'rooms' => 'nullable|integer|min:1|max:8',
            'children_ages' => 'nullable|array|max:8',
            'children_ages.*' => 'integer|min:0|max:17',
        ] + $this->listRules();
    }
}
