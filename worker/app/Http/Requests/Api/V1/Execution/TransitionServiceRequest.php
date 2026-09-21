<?php

namespace App\Http\Requests\Api\V1\Execution;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionServiceRequest extends FormRequest
{
    use RequiresIdempotencyKey;

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
            'action' => [
                'required',
                'in:arrive_pickup,pickup,start_delivery,deliver,arrive,start,complete',
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'out_of_geofence_reason' => ['nullable', 'string', 'max:50'],
            'evidence_id' => ['nullable', 'uuid', 'exists:service_evidences,public_id'],
            'cash_collected' => ['nullable', 'numeric', 'min:0'],
            'cod_collected' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
