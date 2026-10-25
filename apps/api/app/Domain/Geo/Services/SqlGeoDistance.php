<?php

namespace App\Domain\Geo\Services;

use App\Domain\Geo\Contracts\GeoDistance;
use Illuminate\Support\Facades\DB;

/**
 * SQL-backed distance using the engine's spherical function.
 *
 * Verified on the local engine: ST_Distance_Sphere(POINT(lng,lat),…) works on
 * MariaDB 10.4 (probe: Cairo→Giza ≈ 4884.55 m) and on MySQL 8.4. Note the
 * argument order is (longitude, latitude) per the MySQL spatial convention.
 */
class SqlGeoDistance implements GeoDistance
{
    private ?bool $sphereSupported = null;

    public function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if ($this->supportsSphere()) {
            $row = DB::selectOne(
                'SELECT ST_Distance_Sphere(POINT(?, ?), POINT(?, ?)) AS d',
                [$lng1, $lat1, $lng2, $lat2],
            );

            return (float) ($row->d ?? 0.0);
        }

        return $this->haversineMetres($lat1, $lng1, $lat2, $lng2);
    }

    public function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return round($this->metres($lat1, $lng1, $lat2, $lng2) / 1000, 2);
    }

    public function travelEstimate(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        $metres = $this->metres($lat1, $lng1, $lat2, $lng2);

        // Fallback only: 25 km/h effective urban speed + 3 min fixed overhead.
        // Labelled as an estimate so no caller can mistake it for road routing.
        $seconds = (int) round(($metres / 1000) / 25 * 3600 + 180);

        return [
            'seconds' => $seconds,
            'source' => 'distance_fallback',
            'is_estimate' => true,
        ];
    }

    private function supportsSphere(): bool
    {
        if ($this->sphereSupported !== null) {
            return $this->sphereSupported;
        }

        try {
            $row = DB::selectOne('SELECT ST_Distance_Sphere(POINT(0,0), POINT(0,1)) AS d');
            $this->sphereSupported = $row !== null && $row->d !== null;
        } catch (\Throwable) {
            $this->sphereSupported = false;
        }

        return $this->sphereSupported;
    }

    private function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}