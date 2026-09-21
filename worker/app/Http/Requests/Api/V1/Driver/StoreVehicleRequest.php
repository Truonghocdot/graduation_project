<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
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
            'vehicle_type_id' => ['required', 'uuid', 'exists:vehicle_types,public_id'],
            'plate_number' => ['required', 'string', 'max:30', 'unique:vehicles,plate_number'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
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
     * @return array{vehicle_type_id: string, plate_number: string, brand?: string|null, model?: string|null, color?: string|null}
     */
    public function vehicleAttributes(): array
    {
        return [
            'vehicle_type_id' => $this->string('vehicle_type_id')->toString(),
            'plate_number' => $this->string('plate_number')->toString(),
            'brand' => $this->filled('brand') ? $this->string('brand')->toString() : null,
            'model' => $this->filled('model') ? $this->string('model')->toString() : null,
            'color' => $this->filled('color') ? $this->string('color')->toString() : null,
        ];
    }
}
