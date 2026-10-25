<?php
namespace App\Services\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Helpers\OTPHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\NotFoundException;
use Fisal\Otp\Otp;

class PasswordService
{
    public function __construct(public User $model, public Otp $otp)
    {}

    public function forgetPassword($data)
    {
        $user = $this->model->where('phone', $data['phone'])->first();
        if ($user) {
            $otp = $this->otp->generate($data['phone'], 'numeric', 4, 10);
            OTPHelper::sendOtp($data['phone'], $otp->token);
            // $user->code = $otp->token;
            return $user;
        }
        throw new NotFoundException(__('User not found'));
    }

    public function confirmationOtp($data)
    {
        $user = $this->model->where('phone', $data['phone'])->first();
        if (! $user) {
            throw new NotFoundException(__('User not found'));
        }

        if (! $this->otp->validate($data['phone'], $data['otp'])) {
            throw new NotFoundException(__('Invalid or expired OTP'));
        }

        // $user->code = $data['otp'];
        return $user;
    }

    public function resetPassword($data)
    {
        $user = $this->model->where('phone', $data['phone'])->first();
        if (! $user) {
            throw new NotFoundException(__('User not found'));
        }

        $otpStatus = $this->otp->validate($data['phone'], $data['otp']);
        if (! $otpStatus->status) {
            throw new NotFoundException($otpStatus->message);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'email_verified_at' => Carbon::now(),
        ]);

        Auth::login($user);

        return $user;
    }

    public function changePassword($data)
    {
        $user = auth('sanctum')->user();
        $user->update(['password' => Hash::make($data['password'])]);
        return $user;
    }
}
