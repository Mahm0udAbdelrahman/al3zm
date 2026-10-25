<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Travel\ResultsRequest;
use App\Http\Requests\Api\Travel\StaySearchRequest;
use App\Services\Api\Travel\CatalogService;
use App\Services\Api\Travel\StayService;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;

class StayController extends Controller
{
    use HttpResponse;

    public function __construct(public StayService $stayService, public CatalogService $catalogService) {}

    public function cities(Request $request)
    {
        return $this->sendResponse(200, null, $this->catalogService->cities($request->query('q')));
    }

    public function searchDefaults(Request $request)
    {
        return $this->sendResponse(200, null, $this->stayService->defaults($request->query('city')));
    }

    /** Hotels from every stay provider; `data` is the list, `meta` the search. */
    public function search(StaySearchRequest $request)
    {
        $result = $this->stayService->search($request->validated());

        return $this->sendResponse(200, null, $result['hotels'], meta: $result['meta']);
    }

    public function results(ResultsRequest $request, string $searchId)
    {
        $result = $this->stayService->results($searchId, $request->validated());

        return $this->sendResponse(200, null, $result['hotels'], meta: $result['meta']);
    }

    public function show(string $hotelId)
    {
        return $this->sendResponse(200, null, $this->stayService->details($hotelId));
    }
}
