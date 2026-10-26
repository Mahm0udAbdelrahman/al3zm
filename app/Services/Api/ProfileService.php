<?php

namespace App\Services\Api;

use App\Exceptions\NotFoundException;
use App\Traits\HasImage;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    use HasImage;
    public function profile()
    {
        $user = auth('sanctum')->user();
        return $user;
    }

    public function updateProfile($data)
    {
        $user = auth('sanctum')->user();
        if (isset($data['image'])) {
            $data['image'] = $this->saveImage($data['image'], 'user');
        }
        $user->update($data);
        return $user;
    }

    public function changePassword($data)
    {
        $user = auth('sanctum')->user();
        $user->update(['password' => Hash::make($data['password'])]);
        return $user;
    }

    public function logout()
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
            return $user;
        }
        throw new NotFoundException(__('These credentials do not match our records.'));
    }

    public function deleteAccount()
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            throw new NotFoundException(__('User not authenticated'), 401);
        }

        $user->delete();
    }
}
