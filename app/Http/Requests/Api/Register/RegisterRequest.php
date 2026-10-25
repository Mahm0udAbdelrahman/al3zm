<?php

namespace App\Http\Requests\Api\Register;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\HttpResponse;

class RegisterRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone'=> 'required|string|unique:users,phone',
            'image' => 'nullable|image',
            'password' => 'required|string|min:8|confirmed',
            'fcm_token'=> 'required|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {

        throw new HttpResponseException(
            $this->sendResponse(Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors()->first(),$validator->errors())
        );
    }
    // 'errors' => $validator->errors(),
}
