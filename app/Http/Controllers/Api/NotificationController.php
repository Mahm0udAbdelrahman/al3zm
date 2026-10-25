<?php

namespace App\Http\Controllers\Api;

use App\Traits\HttpResponse;
use App\Http\Controllers\Controller;
use App\Services\Api\NotificationService;
use App\Http\Resources\NotificationResouce;


class NotificationController extends Controller
{
    use   HttpResponse;
    public function __construct(public NotificationService $notificationService){}


    public function index()
    {
        $limit = request()->get('limit', 10);
        $data = $this->notificationService->index($limit);
        return $this->paginatedResponse($data, NotificationResouce::class);
    }
}
