<?php

namespace App\Services\Maps;

use App\Data\Maps\Coordinates;
use App\Enums\ServiceType;
use App\Models\ServiceArea;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServiceAreaService
{
    public function assertRouteAvailable(
        ServiceType $serviceType,
        Coordinates $pickup,
        Coordinates $dropoff,
    ): ServiceArea {
        /** @var Collection<int, ServiceArea> $areas */
        $areas = ServiceArea::query()
            ->where('is_active', true)
            ->where(function ($query) use ($serviceType): void {
                $query->whereNull('service_type')
                    ->orWhere('service_type', $serviceType->value);
            })
            ->orderBy('id')
            ->get();

        $area = $areas->first(
            fn (ServiceArea $candidate): bool => $this->contains($candidate->boundary, $pickup)
                && $this->contains($candidate->boundary, $dropoff),
        );

        if ($area === null) {
            throw ValidationException::withMessages([
                'service_area' => ['Điểm lấy hàng và điểm đến phải nằm trong cùng một khu vực hoạt động.'],
            ]);
        }

        return $area;
    }

    /** @param array<string, mixed> $boundary */
    public function contains(array $boundary, Coordinates $point): bool
    {
        $type = $boundary['type'] ?? null;

        if ($type === 'Bounds') {
            return $this->insideBounds($boundary, $point);
        }

        $coordinates = $boundary['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->insidePolygon($coordinates, $point);
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (is_array($polygon) && $this->insidePolygon($polygon, $point)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $boundary */
    private function insideBounds(array $boundary, Coordinates $point): bool
    {
        $southWest = $boundary['southwest'] ?? null;
        $northEast = $boundary['northeast'] ?? null;

        if (! is_array($southWest) || ! is_array($northEast)) {
            return false;
        }

        return $point->latitude >= (float) ($southWest['latitude'] ?? 91)
            && $point->latitude <= (float) ($northEast['latitude'] ?? -91)
            && $point->longitude >= (float) ($southWest['longitude'] ?? 181)
            && $point->longitude <= (float) ($northEast['longitude'] ?? -181);
    }

    /** @param array<int, mixed> $polygon */
    private function insidePolygon(array $polygon, Coordinates $point): bool
    {
        $outerRing = $polygon[0] ?? null;

        if (! is_array($outerRing) || ! $this->insideRing($outerRing, $point)) {
            return false;
        }

        foreach (array_slice($polygon, 1) as $hole) {
            if (is_array($hole) && $this->insideRing($hole, $point)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $ring */
    private function insideRing(array $ring, Coordinates $point): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for ($current = 0, $previous = $count - 1; $current < $count; $previous = $current++) {
            $currentPoint = $ring[$current] ?? null;
            $previousPoint = $ring[$previous] ?? null;

            if (! is_array($currentPoint) || ! is_array($previousPoint)) {
                return false;
            }

            $currentLongitude = (float) ($currentPoint[0] ?? 181);
            $currentLatitude = (float) ($currentPoint[1] ?? 91);
            $previousLongitude = (float) ($previousPoint[0] ?? 181);
            $previousLatitude = (float) ($previousPoint[1] ?? 91);

            if ($this->pointOnSegment(
                $point,
                $previousLatitude,
                $previousLongitude,
                $currentLatitude,
                $currentLongitude,
            )) {
                return true;
            }

            $crossesLatitude = ($currentLatitude > $point->latitude)
                !== ($previousLatitude > $point->latitude);
            $intersectionLongitude = ($previousLongitude - $currentLongitude)
                * ($point->latitude - $currentLatitude)
                / (($previousLatitude - $currentLatitude) ?: PHP_FLOAT_EPSILON)
                + $currentLongitude;

            if ($crossesLatitude && $point->longitude < $intersectionLongitude) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function pointOnSegment(
        Coordinates $point,
        float $startLatitude,
        float $startLongitude,
        float $endLatitude,
        float $endLongitude,
    ): bool {
        $crossProduct = ($point->latitude - $startLatitude) * ($endLongitude - $startLongitude)
            - ($point->longitude - $startLongitude) * ($endLatitude - $startLatitude);

        if (abs($crossProduct) > 0.000000001) {
            return false;
        }

        return $point->latitude >= min($startLatitude, $endLatitude)
            && $point->latitude <= max($startLatitude, $endLatitude)
            && $point->longitude >= min($startLongitude, $endLongitude)
            && $point->longitude <= max($startLongitude, $endLongitude);
    }
}
