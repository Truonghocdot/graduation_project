<?php

namespace App\Services\Booking;

use App\Models\IdempotencyKey;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IdempotencyService
{
    /** @param array<string, mixed> $payload */
    public function begin(User $user, string $scope, string $key, array $payload): IdempotencyKey
    {
        $requestHash = hash('sha256', json_encode($this->normalize($payload), JSON_THROW_ON_ERROR));
        $identity = [
            'actor_type' => 'USER',
            'actor_key' => (string) $user->id,
            'scope' => $scope,
            'key' => $key,
        ];
        $candidate = IdempotencyKey::query()->firstOrCreate($identity, [
            'user_id' => $user->id,
            'request_hash' => $requestHash,
            'status' => 'PENDING',
            'expires_at' => now()->addDay(),
        ]);
        $wasCreated = $candidate->wasRecentlyCreated;
        $record = IdempotencyKey::query()
            ->where($identity)
            ->lockForUpdate()
            ->firstOrFail();

        if ($wasCreated) {
            return $record;
        }

        if ($record->request_hash !== $requestHash) {
            throw new ConflictHttpException(
                'This idempotency key was already used with a different request.',
            );
        }

        if ($record->status === 'COMPLETED') {
            return $record;
        }

        throw new ConflictHttpException(
            'A request with this idempotency key is already in progress.',
        );
    }

    /** @param array<string, mixed> $responseBody */
    public function complete(
        IdempotencyKey $record,
        int $responseCode,
        array $responseBody,
        string $resourceType,
        int $resourceId,
    ): void {
        $record->forceFill([
            'status' => 'COMPLETED',
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ])->save();
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        ksort($value);

        return $value;
    }
}
