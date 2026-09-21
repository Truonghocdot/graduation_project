<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

trait RequiresIdempotencyKey
{
    public function idempotencyKey(): string
    {
        $key = trim((string) $this->header('Idempotency-Key'));

        if ($key === '' || mb_strlen($key) > 191) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['A valid Idempotency-Key header is required.'],
            ]);
        }

        return $key;
    }
}
