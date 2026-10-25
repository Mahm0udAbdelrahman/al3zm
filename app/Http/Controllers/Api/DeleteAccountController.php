<?php

namespace App\Http\Controllers\Api;

use App\Traits\HttpResponse;
use App\Http\Controllers\Controller;
use App\Services\Api\DeleteAccountService;

class DeleteAccountController extends Controller
{
    use   HttpResponse;
    public function __construct(public DeleteAccountService $deleteAccountService){}

    public function deleteAccount()
    {
        $this->deleteAccountService->deleteAccount();
        return $this->sendResponse(200, __('Account deleted successfully'));
    }
}
