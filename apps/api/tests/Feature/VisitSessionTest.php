<?php

namespace Tests\Feature;

use App\Domain\Visits\Services\VisitSessionService;
use App\Models\Booking;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-030 — GPS inside the geofence without the customer's confirmation yields ZERO
 *         started paid time.
 * T-031 — provider arrival + customer confirmation produces exactly one session start.
 * T-034 — time billing: minimum 30 minutes, then exact minutes.
 */
class VisitSessionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VisitSessionService
    {
        return app(VisitSessionService::class);
    }

    private function visit(): array
    {
        $booking = Booking::factory()->create([
            'professional_profile_id' => ProfessionalProfile::factory()->create()->id,
            'state' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $visit = Visit::create([
            'booking_id' => $booking->id,
            'professional_profile_id' => $booking->professional_profile_id,
            'state' => 'scheduled',
        ]);

        return [$visit, $booking, User::factory()->create()];
    }

    public function test_T030_gps_geofence_without_customer_confirmation_yields_zero_billing(): void
    {
        [$visit, , $actor] = $this->visit();

        $svc = $this->service();

        // Provider departs and claims arrival, with GPS right at the address.
        $svc->markDeparted($visit, $actor);
        $svc->claimArrival($visit, $actor, 30.0444, 31.2357, 8.0);

        $visit->refresh();

        // A geofence signal was recorded…
        $this->assertNotEmpty($visit->geofence_observations);
        $this->assertSame('signal_only_never_starts_billing', $visit->geofence_observations[0]['note']);

        // …but NO session started and NO billable minutes exist.
        $this->assertNull($visit->session_started_at);
        $this->assertNull($visit->billed_minutes);
        $this->assertSame('arrival_claimed', $visit->state);
        $this->assertFalse($visit->isSessionRunning());
    }

    public function test_T031_customer_confirmation_starts_exactly_one_session(): void
    {
        [$visit, , $actor] = $this->visit();
        $customer = User::factory()->create();

        $svc = $this->service();
        $svc->markDeparted($visit, $actor);
        $svc->claimArrival($visit, $actor, 30.0444, 31.2357);

        $started = $svc->confirmArrival($visit->fresh(), $customer);

        $this->assertNotNull($started->session_started_at);
        $this->assertSame('in_session', $started->state);
        $this->assertSame(1, $started->timeEvents()->where('kind', 'started')->count());

        // Repeated confirmation is idempotent — still exactly one start event.
        $again = $svc->confirmArrival($started->fresh(), $customer);

        $this->assertSame(
            $started->session_started_at->toIso8601String(),
            $again->session_started_at->toIso8601String(),
        );
        $this->assertSame(1, $again->timeEvents()->where('kind', 'started')->count());
    }

    public function test_customer_cannot_confirm_before_provider_claims_arrival(): void
    {
        [$visit, , $actor] = $this->visit();
        $customer = User::factory()->create();

        $this->expectException(\DomainException::class);
        $this->service()->confirmArrival($visit, $customer);
    }

    public function test_T034_time_billing_uses_thirty_minute_minimum_then_exact_minutes(): void
    {
        [$visit] = $this->visit();
        $svc = $this->service();

        $start = now();

        // 12 minutes → billed at the 30-minute minimum.
        $this->assertSame(30, $svc->billableMinutes($start, $start->copy()->addMinutes(12), $visit));

        // 47 minutes → billed exactly 47.
        $this->assertSame(47, $svc->billableMinutes($start, $start->copy()->addMinutes(47), $visit));

        // 30 minutes exactly → 30.
        $this->assertSame(30, $svc->billableMinutes($start, $start->copy()->addMinutes(30), $visit));
    }

    public function test_completion_records_billed_minutes_from_server_time(): void
    {
        [$visit, , $actor] = $this->visit();
        $customer = User::factory()->create();
        $svc = $this->service();

        $svc->markDeparted($visit, $actor);
        $svc->claimArrival($visit, $actor, 30.0444, 31.2357);
        $svc->confirmArrival($visit->fresh(), $customer);

        // Backdate the start to simulate a 45-minute visit.
        $visit->fresh()->update(['session_started_at' => now()->subMinutes(45)]);

        $completed = $svc->complete($visit->fresh(), $actor);

        $this->assertSame('provider_completed', $completed->state);
        $this->assertGreaterThanOrEqual(44, $completed->billed_minutes);
        $this->assertLessThanOrEqual(46, $completed->billed_minutes);
        $this->assertNotNull($completed->session_ended_at);
    }

    public function test_human_exception_path_is_recorded_distinctly_from_gps(): void
    {
        [$visit, , $actor] = $this->visit();
        $staff = User::factory()->create();

        $svc = $this->service();
        $svc->claimArrival($visit, $actor, 30.0444, 31.2357);

        $resolved = $svc->resolveWithoutCustomer($visit->fresh(), $staff, 'customer_unreachable', 'case-123');

        $this->assertSame('in_session', $resolved->state);
        $this->assertSame('human_exception', $resolved->checkin_evidence['resolution']);
        $this->assertNotNull($resolved->session_started_at);
    }
}