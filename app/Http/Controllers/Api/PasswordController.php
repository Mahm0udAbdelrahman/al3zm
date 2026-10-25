<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Login\ChangePassword;
use App\Http\Requests\Api\Login\ResetPasswordRequest;
use App\Http\Requests\Api\Register\ConfirmationOtpRequest;
use App\Http\Requests\Api\Register\SendCodeRequest;
use App\Http\Resources\RegisterResource;
use App\Services\Api\PasswordService;
use App\Traits\HttpResponse;

class PasswordController extends Controller
{
    use HttpResponse;
    public function __construct(public PasswordService $passwordService)
    {}

    public function forgetPassword(SendCodeRequest $sendCodeRequest)
    {
        $user     = $this->passwordService->forgetPassword($sendCodeRequest->validated());
        $response = ['user' => RegisterResource::make($user), 'otp' => $user->code];
        return $this->sendResponse(200, __('Code sent successfully'), $response);
    }

    public function confirmationOtp(ConfirmationOtpRequest $confirmationOtpRequest)
    {
        $user     = $this->passwordService->confirmationOtp($confirmationOtpRequest->validated());
        $response = ['otp' => $user->code];
        return $this->sendResponse(200, __('Code verified successfully'), $response);
    }

    public function resetPassword(ResetPasswordRequest $resetPasswordRequest)
    {
        $user = $this->passwordService->resetPassword($resetPasswordRequest->validated());
        return $this->sendResponse(200, __('The password has been reset successfully'), RegisterResource::make($user));
    }

    public function changePassword(ChangePassword $changePassword)
    {
        $user = $this->passwordService->changePassword($changePassword->validated());
        return $this->sendResponse(200, __('The password has been changed successfully'), RegisterResource::make($user));
    }

}
