<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Login\LoginRequest;
use App\Http\Requests\Api\Register\CodeRequest;
use App\Http\Requests\Api\Register\RegisterRequest;
use App\Http\Requests\Api\Register\SendCodeRequest;
use App\Http\Resources\UserResource;
use App\Services\Api\LoginService;
use App\Traits\HasImage;
use App\Traits\HttpResponse;

class LoginController extends Controller
{
    use HttpResponse, HasImage;

    public function __construct(public LoginService $loginService)
    {}


        public function register(RegisterRequest $request)
    {
        $data = $this->loginService->register($request->validated());
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }
        return $this->sendResponse(200, __('User registered successfully'), new UserResource($data));
    }

    public function verify(CodeRequest $codeRequest)
    {
        [$user, $token] = $this->loginService->verify($codeRequest->validated());

        $response = [
            'user'  => UserResource::make($user),
            'token' => $token,
        ];
         return $this->sendResponse(
        200, 
        __('User account verified successfully'), 
        $response
    );
    }

    public function login(LoginRequest $loginRequest)
    {

        $result = $this->loginService->login($loginRequest->validated());

        if ($result === 'userNotVerified') {
            return $this->sendResponse(419, __('The user account has not been verified yet'), null, null, 419);
        }

        [$user, $token] = $result;

        $response = [
            'user'  => UserResource::make($user),
            'token' => $token,
        ];
        return $this->sendResponse(200, __('Login successfully'), $response);
    }

    public function resendCode(SendCodeRequest $sendCodeRequest)
    {
        $data = $this->loginService->resendCode($sendCodeRequest->validated());
        return $this->sendResponse(200, __('Code sent successfully'), $data);
    }
    
    


}
