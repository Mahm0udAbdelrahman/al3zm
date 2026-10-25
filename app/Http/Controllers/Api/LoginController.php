<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Login\LoginRequest;
use App\Http\Resources\RegisterResource;
use App\Services\Api\LoginService;
use App\Traits\HasImage;
use App\Traits\HttpResponse;

class LoginController extends Controller
{
    use HttpResponse, HasImage;

    public function __construct(public LoginService $loginService)
    {}

    public function login(LoginRequest $loginRequest)
    {

        [$user, $token] = $this->loginService->login($loginRequest->validated());

        $response = [
            'user'  => RegisterResource::make($user),
            'token' => $token,
        ];
        return $this->sendResponse(200, __('Login successfully'), $response);
    }
}
