<?php

namespace App\Http\Controllers\Api;

use App\Traits\HttpResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Api\LogoutService;
use App\Http\Resources\RegisterResource;

class LogoutController extends Controller
{
    use HttpResponse;

    public function __construct(public LogoutService $logoutService){}

    public function logout()
    {
        $this->logoutService->logout();
        return $this->sendResponse(200, __('You logged out successfully'));
    }


}
