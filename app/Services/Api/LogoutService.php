<?php
namespace App\Services\Api;

use App\Models\User;

use Illuminate\Support\Facades\Hash;
use App\Exceptions\NotFoundException;


class LogoutService
{

    public function logout()
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
            return $user;
        }
        throw new NotFoundException(__('These credentials do not match our records.', [], Request()->header('Accept-language')));
    }

}
