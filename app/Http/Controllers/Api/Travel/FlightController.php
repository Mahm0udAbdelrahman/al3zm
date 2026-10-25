<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Travel\FlightSearchRequest;
use App\Http\Requests\Api\Travel\ResultsRequest;
use App\Services\Api\Travel\CatalogService;
use App\Services\Api\Travel\FlightService;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    use HttpResponse;

    public function __construct(public FlightService $flightService, public CatalogService $catalogService) {}

    public function airports(Request $request)
    {
        return $this->sendResponse(200, null, $this->catalogService->airports($request->query('q')));
    }

    public function searchDefaults(Request $request)
    {
        return $this->sendResponse(200, null, $this->flightService->defaults($request->query('to')));
    }

    /** Every flight provider at once, merged, sorted and paged. */
    public function search(FlightSearchRequest $request)
    {
        return $this->sendResponse(200, null, $this->flightService->search($request->validated()));
    }

    /** The same search re-sorted / filtered / paged, without asking the providers again. */
    public function results(ResultsRequest $request, string $searchId)
    {
        return $this->sendResponse(200, null, $this->flightService->results($searchId, $request->validated()));
    }

    public function offer(string $offerId)
    {
        return $this->sendResponse(200, null, $this->flightService->offer($offerId));
    }

    public function seatMap(string $offerId)
    {
        return $this->sendResponse(200, null, $this->flightService->seatMap($offerId));
    }
}
