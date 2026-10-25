<?php
namespace App\Http\Requests\Dashboard\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'name'         => 'nullable|string|max:255',
            'email'        => ['nullable', 'email'],
            'phone' => [
                'nullable',
                'string',
                'regex:/^[0-9]+$/',
                // 'unique:users,phone,' . $this->id,
            ],
            'password'     => 'nullable|string|min:8',
            'role_id'      => ['nullable', 'exists:roles,id'],
            'is_active'  => ['required', 'in:0,1'],
            'image'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }
}
