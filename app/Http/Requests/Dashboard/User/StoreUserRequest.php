<?php
namespace App\Http\Requests\Dashboard\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
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
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users')],
            'phone'    => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                Rule::unique('users'),
            ],
            'password' => 'required|string|min:8',
            'role_id'  => ['required', 'exists:roles,id'],
            'is_active'  => ['required', 'boolean'],
            'image'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

}
