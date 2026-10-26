<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Login\ChangePassword;
use App\Http\Requests\Api\Login\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Api\ProfileService;
use App\Traits\HttpResponse;

class ProfileController extends Controller
{
    use HttpResponse;
    public function __construct(public ProfileService $profileService) {}
    public function profile()
    {
        $user = $this->profileService->profile();

        return $this->sendResponse(200, __('View profile'), UserResource::make($user));
    }

    public function updateProfile(ProfileRequest $profileRequest)
    {
        $user = $this->profileService->updateProfile($profileRequest->validated());
        return $this->sendResponse(200, __('The user profile has been updated successfully'), UserResource::make($user));
    }
    public function changePassword(ChangePassword $changePassword)
    {
        $user = $this->profileService->changePassword($changePassword->validated());
        return $this->sendResponse(200, __('The password has been changed successfully'), UserResource::make($user));
    }
    public function logout()
    {
        $this->profileService->logout();
        return $this->sendResponse(200, __('You logged out successfully'));
    }

    public function deleteAccount()
    {
        $this->profileService->deleteAccount();
        return $this->sendResponse(200, __('Account deleted successfully'));
    }
}
