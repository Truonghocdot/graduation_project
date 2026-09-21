<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoOnlineRequest extends FormRequest
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
            'service_types' => ['required', 'array', 'min:1', 'max:2'],
            'service_types.*' => ['required', 'distinct', Rule::enum(ServiceType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:1000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'captured_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array{service_types: array<int, string>, latitude: float, longitude: float, accuracy: float, heading?: float|null, speed?: float|null, captured_at: string}
     */
    public function availabilityAttributes(): array
    {
        return [
            'service_types' => $this->array('service_types'),
            'latitude' => $this->float('latitude'),
            'longitude' => $this->float('longitude'),
            'accuracy' => $this->float('accuracy'),
            'heading' => $this->filled('heading') ? $this->float('heading') : null,
            'speed' => $this->filled('speed') ? $this->float('speed') : null,
            'captured_at' => $this->string('captured_at')->toString(),
        ];
    }
}
