<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Keep these errors separate from the other settings form.
     *
     * @var string
     */
    protected $errorBag = 'updatePassword';

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8), 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ];
    }
}
