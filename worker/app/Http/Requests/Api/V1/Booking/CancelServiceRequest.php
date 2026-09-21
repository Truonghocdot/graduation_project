<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequest extends FormRequest
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
            'reason_code' => ['required', 'string', 'max:50'],
        ];
    }
}
