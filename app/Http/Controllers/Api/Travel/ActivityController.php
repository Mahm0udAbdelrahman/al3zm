<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Travel\ActivitySearchRequest;
use App\Http\Requests\Api\Travel\AvailabilityRequest;
use App\Services\Api\Travel\ActivityService;
use App\Services\Api\Travel\CatalogService;
use App\Traits\HttpResponse;

class ActivityController extends Controller
{
    use HttpResponse;

    public function __construct(public ActivityService $activityService, public CatalogService $catalogService) {}

    public function cities()
    {
        return $this->sendResponse(200, null, $this->catalogService->cities());
    }

    public function index(ActivitySearchRequest $request)
    {
        $result = $this->activityService->search($request->validated());

        return $this->sendResponse(200, null, $result['activities'], meta: $result['meta']);
    }

    public function show(string $activityId)
    {
        return $this->sendResponse(200, null, $this->activityService->details($activityId));
    }

    public function availability(AvailabilityRequest $request, string $activityId)
    {
        $from = $request->validated('from');

        return $this->sendResponse(200, null, $this->activityService->availability(
            $activityId,
            substr($from, 0, 10),
            substr($request->validated('to') ?? $from, 0, 10),
        ));
    }
}
