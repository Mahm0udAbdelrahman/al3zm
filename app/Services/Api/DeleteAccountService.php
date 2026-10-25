<?php
namespace App\Services\Api;

class DeleteAccountService
{

    public function deleteAccount()
    {
        $user = auth()->user();

        if (! $user) {
            throw new NotFoundException(__('User not authenticated'),401);
        }

        $user->delete();
    }

}
