<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Services\Travel\ProviderRegistry;
use App\Traits\HttpResponse;

class TravelController extends Controller
{
    use HttpResponse;

    /** Which providers are switched on, per vertical. */
    public function providers(ProviderRegistry $registry)
    {
        return $this->sendResponse(200, null, [
            'currency' => config('travel.currency'),
            'providers' => $registry->status(),
        ]);
    }
}
