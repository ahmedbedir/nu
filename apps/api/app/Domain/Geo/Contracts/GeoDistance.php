<?php

namespace App\Domain\Geo\Contracts;

/**
 * Distance/ETA boundary.
 *
 * The application never branches on the database engine: MariaDB locally and
 * Oracle MySQL in production both satisfy this contract through the SQL adapter,
 * and a routing provider can later implement the ETA side (ADR-002).
 */
interface GeoDistance
{
    /** Great-circle distance in metres between two WGS84 points. */
    public function metres(float $lat1, float $lng1, float $lat2, float $lng2): float;

    /** Kilometres, rounded for display. */
    public function km(float $lat1, float $lng1, float $lat2, float $lng2): float;

    /**
     * Estimated travel seconds. Until a real routing vendor is benchmarked and
     * signed off (#220), this is a labelled fallback derived from distance —
     * it must never be presented as a measured road ETA.
     *
     * @return array{seconds: int, source: string, is_estimate: bool}
     */
    public function travelEstimate(float $lat1, float $lng1, float $lat2, float $lng2): array;
}