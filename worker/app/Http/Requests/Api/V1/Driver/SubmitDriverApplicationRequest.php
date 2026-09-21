<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDriverApplicationRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid'],
            'service_types' => ['required', 'array', 'min:1', 'max:2'],
            'service_types.*' => ['required', 'distinct', Rule::enum(ServiceType::class)],
        ];
    }
}
