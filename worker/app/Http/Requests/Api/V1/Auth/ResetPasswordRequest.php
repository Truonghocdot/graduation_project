<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    use NormalizesPhoneNumber;

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
            'phone' => ['required', 'string', 'regex:/^\+84\d{9}$/'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
