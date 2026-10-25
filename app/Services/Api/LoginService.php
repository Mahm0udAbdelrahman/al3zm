<?php
namespace App\Services\Api;

use App\Models\User;

use Illuminate\Support\Facades\Hash;
use App\Exceptions\NotFoundException;


class LoginService
{


    public function __construct(public User $user){}


        public function login($data)
    {

        $user = User::where('phone', $data['phone'])->first();
       
        if (!$user) {
            throw new NotFoundException(__('These credentials dow not match our records.'));
        }

        if($user->email_verified_at == null){
            throw new NotFoundException(
                __('The user account has not been verified yet'),
                403
            );
        }
        if ($user && Hash::check($data['password'], $user->password)) {

            $user->update(['fcm_token' => $data['fcm_token'] ]);
            $token = $user->createToken("API TOKEN")->plainTextToken;
            return [$user , $token];

        }

        throw new NotFoundException(__('These credentials do not match our records.'));
    }

}
