<?php

namespace App\Data\Maps;

final readonly class RouteResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $provider,
        public float $distanceMeters,
        public int $durationSeconds,
        public ?string $encodedPolyline,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'distance_meters' => $this->distanceMeters,
            'duration_seconds' => $this->durationSeconds,
            'encoded_polyline' => $this->encodedPolyline,
            'metadata' => $this->metadata,
        ];
    }
}
