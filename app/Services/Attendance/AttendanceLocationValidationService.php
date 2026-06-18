<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\WorkLocation;

class AttendanceLocationValidationService
{
    public function __construct(
        private readonly AttendancePolicyResolverService $attendancePolicyResolverService,
    ) {
    }

    /**
     * @return array{is_valid: bool, message: string|null}
     */
    public function validate(
        Employee $employee,
        ?float $latitude,
        ?float $longitude,
        ?WorkLocation $workLocation = null,
    ): array {
        $policy = $this->attendancePolicyResolverService->resolvePolicy($employee);
        $locationMode = $this->attendancePolicyResolverService->resolveLocationMode($employee);
        $gpsRequired = (bool) ($policy?->gps_required ?? false);
        $radiusValidationEnabled = (bool) ($policy?->radius_validation_enabled ?? false);

        if (($latitude === null) xor ($longitude === null)) {
            return $this->invalid('GPS coordinates are incomplete.');
        }

        if ($latitude === null || $longitude === null) {
            if ($gpsRequired) {
                return $this->invalid('GPS coordinates are required for attendance logging.');
            }

            if ($locationMode === AttendancePolicy::LOCATION_MODE_FLEXIBLE) {
                return $this->valid();
            }

            if ($radiusValidationEnabled) {
                return $this->invalid('GPS coordinates are required for location validation.');
            }

            return $this->valid();
        }

        if ($locationMode === AttendancePolicy::LOCATION_MODE_FLEXIBLE) {
            return $this->valid();
        }

        $targetLocation = match ($locationMode) {
            AttendancePolicy::LOCATION_MODE_SCHEDULED => $workLocation,
            default => $employee->relationLoaded('workLocation')
                ? $employee->workLocation
                : $employee->workLocation()->first(),
        };

        if (! $targetLocation instanceof WorkLocation) {
            return $this->invalid('No work location is configured for attendance validation.');
        }

        if (! $radiusValidationEnabled) {
            return $this->valid();
        }

        if ($targetLocation->latitude === null || $targetLocation->longitude === null) {
            return $this->invalid('The resolved work location has no GPS coordinates for validation.');
        }

        $radiusMeters = $policy?->radius_meters ?: $targetLocation->radius_meters;

        if (! filled($radiusMeters)) {
            return $this->valid();
        }

        $distanceMeters = $this->calculateDistanceMeters(
            $latitude,
            $longitude,
            (float) $targetLocation->latitude,
            (float) $targetLocation->longitude,
        );

        if ($distanceMeters > (float) $radiusMeters) {
            return $this->invalid(sprintf(
                'Clock location is outside the allowed radius (%.1fm > %dm).',
                $distanceMeters,
                $radiusMeters,
            ));
        }

        return $this->valid();
    }

    private function calculateDistanceMeters(
        float $originLatitude,
        float $originLongitude,
        float $targetLatitude,
        float $targetLongitude,
    ): float {
        $earthRadius = 6371000.0;
        $latitudeDelta = deg2rad($targetLatitude - $originLatitude);
        $longitudeDelta = deg2rad($targetLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude))
            * cos(deg2rad($targetLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /**
     * @return array{is_valid: true, message: null}
     */
    private function valid(): array
    {
        return [
            'is_valid' => true,
            'message' => null,
        ];
    }

    /**
     * @return array{is_valid: false, message: string}
     */
    private function invalid(string $message): array
    {
        return [
            'is_valid' => false,
            'message' => $message,
        ];
    }
}
