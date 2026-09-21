<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRideBookingRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->status === UserStatus::Active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid', Rule::exists('quotes', 'public_id')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'stops' => ['sometimes', 'array:pickup,dropoff'],
            'stops.pickup' => ['sometimes', 'array:note'],
            'stops.pickup.note' => ['nullable', 'string', 'max:1000'],
            'stops.dropoff' => ['sometimes', 'array:note'],
            'stops.dropoff.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
