<?php

namespace App\Http\Requests\Api\V1\Booking;

use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryOrderRequest extends FormRequest
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
            'payer_type' => ['required', Rule::enum(PayerType::class)],
            'recipient_user_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn (): bool => $this->input('payer_type') === PayerType::Recipient->value),
                Rule::exists('users', 'public_id')->where(
                    'status',
                    UserStatus::Active->value,
                ),
            ],
            'stops' => ['sometimes', 'array:pickup,dropoff'],
            'stops.pickup' => ['sometimes', 'array:contact_name,contact_phone,note'],
            'stops.pickup.contact_name' => ['nullable', 'string', 'max:120'],
            'stops.pickup.contact_phone' => ['nullable', 'string', 'max:20'],
            'stops.pickup.note' => ['nullable', 'string', 'max:1000'],
            'stops.dropoff' => ['sometimes', 'array:contact_name,contact_phone,note'],
            'stops.dropoff.contact_name' => ['nullable', 'string', 'max:120'],
            'stops.dropoff.contact_phone' => ['nullable', 'string', 'max:20'],
            'stops.dropoff.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
