<?php
namespace App\Services\Api;

use App\Exceptions\NotFoundException;
use App\Helpers\OTPHelper;
use App\Models\User;
use App\Traits\HasImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Fisal\Otp\Otp;

class RegisterService
{
    use HasImage;



    public function __construct(public User $model ,public Otp $otp)
    {}

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

        $user = $this->model->create($data);

        return $user;
    }

    public function verify($data)
    {
       
        $user = $this->model->where('phone', $data['phone'])->first();

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

    public function otp($data)
    {
        $user = $this->model->where('phone', $data['phone'])
            ->whereNotNull('code')
            ->whereNotNull('expire_at')
            ->first();

        if ($user) {
            if (now()->greaterThan($user->expire_at)) {
                $newCode       = rand(1000, 9999);
                $user->update([
                    'code'      => $newCode,
                    'expire_at' => now()->addMinutes(1),
                ]);

                OTPHelper::sendOtp($data['phone'], $newCode);
            }
            return $user;
        }
        throw new NotFoundException(__('The Phone is Verify', [], request()->header('Accept-language')), 400);


    }

}
