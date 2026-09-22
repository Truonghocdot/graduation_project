<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
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
            'incident_type' => [
                'required',
                'in:SOS,SAFETY,ACCIDENT,DELIVERY_FAILED,NO_SHOW,ITEM_DAMAGE,OTHER',
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'evidence_ids' => ['nullable', 'array', 'max:10'],
            'evidence_ids.*' => ['uuid', 'exists:service_evidences,public_id'],
        ];
    }
}
