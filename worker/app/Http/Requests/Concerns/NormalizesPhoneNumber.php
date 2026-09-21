<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

trait NormalizesPhoneNumber
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => PhoneNumber::normalize($this->string('phone')->toString()),
            ]);
        }
    }
}
