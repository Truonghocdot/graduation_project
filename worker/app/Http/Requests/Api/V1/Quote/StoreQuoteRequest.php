<?php

namespace App\Http\Requests\Api\V1\Quote;

use App\Enums\BookingType;
use App\Enums\ServiceType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->status === UserStatus::Active;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $isDelivery = $this->input('service_type') === ServiceType::Delivery->value;
        $isDrive = $this->input('service_type') === ServiceType::Drive->value;
        $isScheduled = $this->input('booking_type') === BookingType::Scheduled->value;

        return [
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'vehicle_type_id' => [
                'required',
                'uuid',
                Rule::exists('vehicle_types', 'public_id')->where('is_active', true),
            ],
            'booking_type' => ['required', Rule::enum(BookingType::class)],
            'scheduled_at' => [
                Rule::requiredIf($isScheduled),
                Rule::prohibitedIf(! $isScheduled),
                'date',
                'after:now',
            ],
            'pickup' => ['required', 'array:address,latitude,longitude,note'],
            'pickup.address' => ['required', 'string', 'max:500'],
            'pickup.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup.note' => ['nullable', 'string', 'max:500'],
            'dropoff' => ['required', 'array:address,latitude,longitude,note'],
            'dropoff.address' => ['required', 'string', 'max:500'],
            'dropoff.latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff.note' => ['nullable', 'string', 'max:500'],
            'service_payload' => [
                'required',
                'array:passenger_count,goods_type,goods_description,weight_kg,length_cm,width_cm,height_cm,declared_value,is_cod,cod_amount',
            ],
            'service_payload.passenger_count' => [
                Rule::requiredIf($isDrive),
                Rule::prohibitedIf($isDelivery),
                'integer',
                'min:1',
            ],
            'service_payload.goods_type' => [
                Rule::requiredIf($isDelivery),
                Rule::prohibitedIf($isDrive),
                'string',
                'max:50',
            ],
            'service_payload.goods_description' => [Rule::prohibitedIf($isDrive), 'nullable', 'string', 'max:1000'],
            'service_payload.weight_kg' => [Rule::prohibitedIf($isDrive), 'nullable', 'numeric', 'min:0'],
            'service_payload.length_cm' => [Rule::prohibitedIf($isDrive), 'nullable', 'numeric', 'min:0'],
            'service_payload.width_cm' => [Rule::prohibitedIf($isDrive), 'nullable', 'numeric', 'min:0'],
            'service_payload.height_cm' => [Rule::prohibitedIf($isDrive), 'nullable', 'numeric', 'min:0'],
            'service_payload.declared_value' => [Rule::prohibitedIf($isDrive), 'nullable', 'numeric', 'min:0'],
            'service_payload.is_cod' => [Rule::prohibitedIf($isDrive), 'nullable', 'boolean'],
            'service_payload.cod_amount' => [
                Rule::prohibitedIf($isDrive),
                Rule::requiredIf($isDelivery && $this->boolean('service_payload.is_cod')),
                'numeric',
                'min:1',
                'max:8000000',
            ],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
