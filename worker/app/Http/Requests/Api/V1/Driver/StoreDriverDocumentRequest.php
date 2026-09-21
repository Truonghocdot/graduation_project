<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\DriverDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreDriverDocumentRequest extends FormRequest
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
            'document_type' => ['required', Rule::enum(DriverDocumentType::class)],
            'document_number' => ['nullable', 'string', 'max:100'],
            'vehicle_id' => ['nullable', 'uuid'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'file' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max('5mb')],
        ];
    }

    /**
     * @return array{document_type: string, document_number?: string|null, vehicle_id?: string|null, expires_at?: string|null}
     */
    public function documentAttributes(): array
    {
        return [
            'document_type' => $this->string('document_type')->toString(),
            'document_number' => $this->filled('document_number')
                ? $this->string('document_number')->toString()
                : null,
            'vehicle_id' => $this->filled('vehicle_id')
                ? $this->string('vehicle_id')->toString()
                : null,
            'expires_at' => $this->filled('expires_at')
                ? $this->string('expires_at')->toString()
                : null,
        ];
    }
}
