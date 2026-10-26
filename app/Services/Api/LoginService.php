<?php

namespace App\Services\Api;

use App\Models\User;

use Illuminate\Support\Facades\Hash;
use App\Exceptions\NotFoundException;
use App\Helpers\OTPHelper;
use App\Traits\HasImage;
use Carbon\Carbon;
use Fisal\Otp\Otp;
use Illuminate\Support\Facades\Auth;

class LoginService
{

    use HasImage;
    public function __construct(public User $user, public Otp $otp) {}

    public function register($data)
    {

        if (isset($data['image'])) {
            $data['image'] = $this->saveImage($data['image'], 'user');
        } else {
            $data['image'] = 'default/default.png';
        }

        $data['password']  = Hash::make($data['password']);
        $otp = $this->otp->generate($data['phone'], 'numeric', 4, 10);
        OTPHelper::sendOtp($data['phone'], $otp->token);

        $user = $this->user->create($data);

        return $user;
    }

    public function verify($data)
    {

        $user = $this->user->where('phone', $data['phone'])->first();

        if (! $user) {
            throw new NotFoundException(__('User not found'));
        }

        if ($user->email_verified_at) {
            throw new NotFoundException(__('User is already verified'));
        }

        $otpStatus = $this->otp->validate($data['phone'], $data['otp']);
        if (! $otpStatus->status) {
            throw new NotFoundException($otpStatus->message);
        }

        $token = $user->createToken("API TOKEN")->plainTextToken;
        $user->update([
            'email_verified_at' => Carbon::now(),
        ]);

        Auth::login($user);

        return [$user, $token];
    }

    public function login($data)
    {

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            throw new NotFoundException(__('These credentials dow not match our records.'));
        }

        if ($user->email_verified_at == null) {
            $otp = $this->otp->generate($data['phone'], 'numeric', 4, 10);
            OTPHelper::sendOtp($data['phone'], $otp->token);
            return 'userNotVerified';
        }
        if ($user && Hash::check($data['password'], $user->password)) {
            if (isset($data['fcm_token'])) {
                $user->update(['fcm_token' => $data['fcm_token']]);
            }
            $token = $user->createToken("API TOKEN")->plainTextToken;
            return [$user, $token];
        }

        throw new NotFoundException(__('These credentials do not match our records.'));
    }

    public function resendCode($data)
    {
        $user = User::where('phone', $data['phone'])->first();
        if (!$user) {
            throw new NotFoundException(__('User not found'));
        }

        $otp = $this->otp->generate($data['phone'], 'numeric', 4, 10);
        OTPHelper::sendOtp($data['phone'], $otp->token);
        $data['phone'] = $user->phone;
        if (app()->environment('local')) {
            $data['otp'] = $otp->token;
        }
        return $data;
    }
}
