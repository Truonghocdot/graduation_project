<?php

namespace App\Http\Requests\Api\V1\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => [
                'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf',
                'extensions:jpg,jpeg,png,webp,pdf', 'max:10240',
            ],
        ];
    }
}
