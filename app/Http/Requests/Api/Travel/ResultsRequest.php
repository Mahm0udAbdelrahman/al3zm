<?php

namespace App\Http\Requests\Api\Travel;

class ResultsRequest extends TravelRequest
{
    public function rules(): array
    {
        return $this->listRules();
    }
}
