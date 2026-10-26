<?php

namespace App\Services\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Helpers\OTPHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\NotFoundException;
use Fisal\Otp\Otp;
use Illuminate\Support\Facades\Cache;

class PasswordService
{
    public function __construct(public User $model, public Otp $otp) {}

    public function forgetPassword($data)
    {
        $user = $this->model->where('phone', $data['phone'])->first();
        if ($user) {
            $otp = $this->otp->generate($data['phone'], 'numeric', 4, 10);
            OTPHelper::sendOtp($data['phone'], $otp->token);
            $data['phone'] = $user->phone;
            if (app()->environment('local')) {
                $data['code'] = $otp->token;
            }
            return $data;
        }
        throw new NotFoundException(__('User not found'));
    }

    public function confirmationOtp($data)
    {

        $user = $this->model->where('phone', $data['phone'])->first();
        if (! $user) {
            throw new NotFoundException(__('User not found'));
        }
        $validate = $this->otp->validate($data['phone'], $data['otp']);
        if (! $validate->status) {
            throw new NotFoundException($validate->message);
        }
        Cache::put('reset_password_' . $user->id, $user->phone, 300);
        $result['phone'] = $user->phone;
        return $result;
    }

    public function resetPassword($data)
    {
        $user = $this->model->where('phone', $data['phone'])->first();
        if (! $user) {
            throw new NotFoundException(__('User not found'));
        }

        // $otpStatus = $this->otp->validate($data['phone'], $data['otp']);
        $otpStatus = Cache::get('reset_password_' . $user->id);
        if (! $otpStatus || $otpStatus !== $user->phone) {
            throw new NotFoundException(__('Invalid or expired OTP'));
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        Cache::forget('reset_password_' . $user->id);
        return $user;
    }

    public function changePassword($data)
    {
        $user = auth('sanctum')->user();
        $user->update(['password' => Hash::make($data['password'])]);
        return $user;
    }
}
