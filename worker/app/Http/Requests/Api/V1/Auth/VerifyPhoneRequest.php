<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\AppType;
use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPhoneRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6'],
            'device_id' => ['required', 'string', 'max:191'],
            'app_type' => ['required', Rule::enum(AppType::class)],
            'platform' => ['required', Rule::in(['ANDROID', 'IOS', 'WEB'])],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array{device_id: string, app_type: string, platform: string, push_token?: string|null}
     */
    public function deviceContext(): array
    {
        return [
            'device_id' => $this->string('device_id')->toString(),
            'app_type' => $this->string('app_type')->toString(),
            'platform' => $this->string('platform')->toString(),
            'push_token' => $this->filled('push_token')
                ? $this->string('push_token')->toString()
                : null,
        ];
    }
}
