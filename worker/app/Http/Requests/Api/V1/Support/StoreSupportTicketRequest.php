<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
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
            'service_request_id' => ['nullable', 'uuid', 'exists:service_requests,public_id'],
            'category' => [
                'required',
                'in:PRICING,PAYMENT,ATTITUDE,LOST_ITEM,DAMAGE,SAFETY,OTHER',
            ],
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => [
                'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf',
                'extensions:jpg,jpeg,png,webp,pdf', 'max:10240',
            ],
        ];
    }
}
