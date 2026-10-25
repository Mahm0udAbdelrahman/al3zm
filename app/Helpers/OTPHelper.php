<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class OTPHelper
{
    public static function sendOtp($phone, $otp)
    {

        $country_number = ltrim($phone, '+');

        try {
            $response = Http::withHeaders([
                'beon-token' => 'mBheq217irWK8RErxXwu8kkYDKzKOmyBkXo01r6djbrDyrsL0r0Nmuof2Q1a  123456789',
                'Accept'     => 'application/json',
            ])->post('https://v3.api.beon.chat/api/v3/messages/otp', [
                'phoneNumber' => $phone,
                'name'        => $request->name ?? 'user',
                'type'        => 'sms',
                'lang'        => 'ar',
                'custom_code' => $otp,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Failed to send OTP',
                    'error'   => $response->json(),
                ], 203);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 203);
        }
    }

}
