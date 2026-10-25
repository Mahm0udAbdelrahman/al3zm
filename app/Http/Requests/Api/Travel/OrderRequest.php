<?php

namespace App\Http\Requests\Api\Travel;

/**
 * One order, any mix of items. Each item carries the offer id the search
 * (or hotel details / activity availability) returned, plus who travels.
 */
class OrderRequest extends TravelRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:10',
            'items.*.type' => 'required|in:flight,stay,activity',
            'items.*.offer_id' => 'required|string|max:500',

            // Flights
            'items.*.passengers' => 'required_if:items.*.type,flight|array|max:9',
            'items.*.passengers.*.type' => 'required|in:adult,child,infant',
            'items.*.passengers.*.title' => 'nullable|in:mr,ms,mrs,miss,dr',
            'items.*.passengers.*.gender' => 'required|in:m,f',
            'items.*.passengers.*.given_name' => 'required|string|max:60',
            'items.*.passengers.*.family_name' => 'required|string|max:60',
            'items.*.passengers.*.born_on' => 'required|date|before:today',
            'items.*.passengers.*.email' => 'nullable|email',
            'items.*.passengers.*.phone_number' => 'nullable|string|max:20',
            'items.*.passengers.*.passport_number' => 'nullable|string|max:20',
            'items.*.passengers.*.passport_country' => 'nullable|string|size:2',
            'items.*.passengers.*.passport_expires_on' => 'nullable|date|after:today',
            'items.*.services' => 'nullable|array',
            'items.*.services.*.id' => 'required|string',
            'items.*.services.*.quantity' => 'nullable|integer|min:1|max:5',

            // Stays
            'items.*.guests' => 'required_if:items.*.type,stay|array|min:1',
            'items.*.guests.*.given_name' => 'required|string|max:60',
            'items.*.guests.*.family_name' => 'required|string|max:60',
            'items.*.email' => 'required_if:items.*.type,stay|nullable|email',
            'items.*.phone_number' => 'required_if:items.*.type,stay|nullable|string|max:20',

            // Activities
            'items.*.participants' => 'required_if:items.*.type,activity|array|min:1',
            'items.*.participants.*.category_id' => 'required',
            'items.*.participants.*.quantity' => 'required|integer|min:1|max:20',
            'items.*.traveler' => 'required_if:items.*.type,activity|array',
            'items.*.traveler.given_name' => 'required_with:items.*.traveler|string|max:60',
            'items.*.traveler.family_name' => 'required_with:items.*.traveler|string|max:60',
            'items.*.traveler.email' => 'required_with:items.*.traveler|email',
            'items.*.traveler.phone_number' => 'required_with:items.*.traveler|string|max:20',

            'contact.email' => 'nullable|email',
            'contact.phone' => 'nullable|string|max:20',
            'accept_price_change' => 'nullable|boolean',
        ];
    }
}
