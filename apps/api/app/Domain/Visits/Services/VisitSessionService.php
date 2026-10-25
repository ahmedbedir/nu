<?php

namespace App\Domain\Visits\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Geo\Contracts\GeoDistance;
use App\Models\Visit;
use App\Models\VisitCheckin;
use App\Models\VisitTimeEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Visit execution: departure, arrival, dual check-in and the billed session.
 *
 * NON-NEGOTIABLE (decisions #169–#172, T-030, T-031):
 *  - the provider tapping "arrived" and GPS inside the geofence only produce a
 *    `geofence_observed` signal — they NEVER start the paid session;
 *  - the session starts only when the CUSTOMER separately confirms arrival;
 *  - billing is derived from verified server timestamps, not client clocks;
 *  - a correction to recorded time is an append-only compensating event with a reason.
 */
class VisitSessionService
{
    /** Radius used only to *observe* proximity, never to confirm arrival. */
    private const GEOFENCE_METRES = 150;

    public function __construct(
        private readonly GeoDistance $geo,
        private readonly AuditLogger $audit,
    ) {}

    public function setDepartureEta(Visit $visit, int $minutes, User $actor): Visit
    {
        $max = (int) config('homecare.booking.min_advance_minutes');

        if ($minutes < 0 || $minutes > max($max, 240)) {
            throw new \DomainException('قيمة وقت التحرك غير مسموحة');
        }

        $visit->update(['state' => 'departing', 'departure_eta' => now()->addMinutes($minutes)]);

        return $this->record($visit, 'departure_eta_set', $actor, ['minutes' => $minutes]);
    }

    public function markDeparted(Visit $visit, User $actor): Visit
    {
        $visit->update(['state' => 'en_route', 'departed_at' => now()]);
        $this->audit->log('visit.departed', $actor, 'visit', $visit->public_id, 'visit_execution');

        return $this->record($visit, 'departed', $actor);
    }

    /**
     * Provider claims arrival. Records a geofence observation if coordinates are
     * supplied, but never starts billing.
     */
    public function claimArrival(Visit $visit, User $actor, ?float $lat = null, ?float $lng = null, ?float $accuracy = null): Visit
    {
        $visit->update(['state' => 'arrival_claimed', 'arrival_claimed_at' => now()]);

        VisitCheckin::create([
            'visit_id' => $visit->id,
            'party' => 'provider',
            'kind' => 'claimed',
            'latitude' => $lat,
            'longitude' => $lng,
            'occurred_at' => now(),
        ]);

        if ($lat !== null && $lng !== null) {
            $this->recordGeofenceObservation($visit, $lat, $lng, $accuracy);
        }

        return $this->record($visit, 'arrival_claimed', $actor);
    }

    /**
     * Customer separately confirms arrival — THIS is the only path that starts the
     * billed session together with the provider's claim (T-030/T-031).
     */
    public function confirmArrival(Visit $visit, User $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor) {
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->arrival_claimed_at === null) {
                throw new \DomainException('لا يمكن تأكيد الوصول قبل تسجيل وصول الممرض');
            }

            if ($locked->arrival_confirmed_at !== null) {
                return $locked; // idempotent: repeated confirmation is a no-op
            }

            $now = now();

            VisitCheckin::create([
                'visit_id' => $locked->id,
                'party' => 'customer',
                'kind' => 'confirmed',
                'occurred_at' => $now,
            ]);

            $locked->update([
                'state' => 'in_session',
                'arrival_confirmed_at' => $now,
                'session_started_at' => $now,
                'checkin_evidence' => [
                    'claimed_at' => $locked->arrival_claimed_at?->toIso8601String(),
                    'confirmed_at' => $now->toIso8601String(),
                    'confirmed_by' => $actor->public_id,
                ],
            ]);

            VisitTimeEvent::create([
                'visit_id' => $locked->id,
                'kind' => 'started',
                'occurred_at' => $now,
                'actor_user_id' => $actor->id,
            ]);

            $this->audit->log('visit.arrival_confirmed', $actor, 'visit', $locked->public_id, 'visit_execution');

            return $locked->fresh();
        });
    }

    /**
     * Manually resolves an unreachable-customer case. This is a human, evidence-based
     * exception — it is recorded as such and never presented as a GPS confirmation.
     */
    public function resolveWithoutCustomer(Visit $visit, User $staff, string $reason, string $evidenceRef): Visit
    {
        return DB::transaction(function () use ($visit, $staff, $reason, $evidenceRef) {
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->session_started_at !== null) {
                throw new \DomainException('الجلسة بدأت بالفعل');
            }

            $now = now();

            $locked->update([
                'state' => 'in_session',
                'arrival_confirmed_at' => $now,
                'session_started_at' => $now,
                'checkin_evidence' => [
                    'resolution' => 'human_exception',
                    'reason' => $reason,
                    'evidence_ref' => $evidenceRef,
                    'resolved_by' => $staff->public_id,
                    'resolved_at' => $now->toIso8601String(),
                ],
            ]);

            VisitTimeEvent::create([
                'visit_id' => $locked->id,
                'kind' => 'started',
                'occurred_at' => $now,
                'actor_user_id' => $staff->id,
                'reason' => 'human_exception: '.$reason,
            ]);

            $this->audit->log('visit.started_by_exception', $staff, 'visit', $locked->public_id, 'visit_execution', [
                'reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    /** Provider completes the visit; computes the billable minutes from server time. */
    public function complete(Visit $visit, User $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor) {
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->session_started_at === null) {
                throw new \DomainException('لا يمكن إنهاء زيارة لم تبدأ');
            }

            $now = now();
            $minutes = $this->billableMinutes($locked->session_started_at, $now, $locked);

            $locked->update([
                'state' => 'provider_completed',
                'session_ended_at' => $now,
                'billed_minutes' => $minutes,
            ]);

            VisitTimeEvent::create([
                'visit_id' => $locked->id,
                'kind' => 'stopped',
                'occurred_at' => $now,
                'actor_user_id' => $actor->id,
            ]);

            $this->audit->log('visit.completed', $actor, 'visit', $locked->public_id, 'visit_execution', [
                'billed_minutes' => $minutes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Time-based billing: the first 30 minutes are the minimum, then per-minute
     * (decisions #172, #187, T-034).
     */
    public function billableMinutes(\DateTimeInterface $start, \DateTimeInterface $end, ?Visit $visit = null): int
    {
        $actual = (int) ceil(($end->getTimestamp() - $start->getTimestamp()) / 60);
        $minimum = (int) config('homecare.billing.time_minimum_minutes', 30);

        return max($actual, $minimum);
    }

    private function recordGeofenceObservation(Visit $visit, float $lat, float $lng, ?float $accuracy): void
    {
        $observations = $visit->geofence_observations ?? [];
        $observations[] = [
            'at' => now()->toIso8601String(),
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracy,
            'note' => 'signal_only_never_starts_billing',
        ];

        $visit->update(['geofence_observations' => $observations]);
    }

    private function record(Visit $visit, string $event, User $actor, array $extra = []): Visit
    {
        $this->audit->log('visit.'.$event, $actor, 'visit', $visit->public_id, 'visit_execution', $extra);

        return $visit->fresh();
    }
}