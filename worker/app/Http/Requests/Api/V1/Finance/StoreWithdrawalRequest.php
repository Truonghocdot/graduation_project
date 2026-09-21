<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
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
            'bank_account_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:50000', 'max:50000000'],
        ];
    }
}
