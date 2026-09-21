<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
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
            'vehicle_type_id' => ['sometimes', 'uuid', 'exists:vehicle_types,public_id'],
            'plate_number' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('vehicles', 'plate_number')->ignore($this->route('vehicle'), 'public_id'),
            ],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge([
                'plate_number' => mb_strtoupper(preg_replace('/\s+/', '', $this->string('plate_number')->toString()) ?? ''),
            ]);
        }
    }

    /**
     * @return array{vehicle_type_id?: string, plate_number?: string, brand?: string|null, model?: string|null, color?: string|null}
     */
    public function vehicleAttributes(): array
    {
        $attributes = [];

        foreach (['vehicle_type_id', 'plate_number', 'brand', 'model', 'color'] as $field) {
            if ($this->exists($field)) {
                $attributes[$field] = $this->filled($field)
                    ? $this->string($field)->toString()
                    : null;
            }
        }

        return $attributes;
    }
}
