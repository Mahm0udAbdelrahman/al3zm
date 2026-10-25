<?php
namespace App\Services\Api;

class DeleteAccountService
{

    public function deleteAccount()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'User not authenticated', 'status' => false,], 401);
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted successfully', 'status' => true], 200);
    }

}
