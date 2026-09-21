<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+84\d{9}$/', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{name: string, phone: string, email: string|null, password: string}
     */
    public function registrationAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'phone' => $this->string('phone')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'password' => $this->string('password')->toString(),
        ];
    }
}
