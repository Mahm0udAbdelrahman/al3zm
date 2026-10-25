<?php

namespace App\Http\Requests\Api\Login;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\HttpResponse;

class LoginRequest extends FormRequest
{
    use HttpResponse;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone'=>'required|exists:users,phone',
            'password' => 'required|string|min:8',
            'fcm_token' => 'required',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            $this->sendResponse(Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors()->first(),$validator->errors())
        );
    }
}
