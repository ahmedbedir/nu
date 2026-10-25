<?php

namespace Tests\Feature;

use App\Domain\Safety\Services\SosService;
use App\Models\Booking;
use App\Models\ProfessionalProfile;
use App\Models\SosIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-057 / T-058 / T-059 / T-060 — SOS truthfulness and escalation.
 */
class SosTest extends TestCase
{
    use RefreshDatabase;

    private function sos(): SosService
    {
        return app(SosService::class);
    }

    public function test_T059_acknowledge_and_resolve_are_separate_events(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();

        $incident = $this->sos()->initiate('customer', $customer);

        $this->assertSame('accepted_server', $incident->state);
        $this->assertFalse($incident->isAcknowledged());

        $acked = $this->sos()->acknowledge($incident, $staff, 'internal', 'تم التواصل');

        $this->assertTrue($acked->isAcknowledged());
        $this->assertSame('acknowledged', $acked->state);
        $this->assertNull($acked->closed_at, 'acknowledgement must not close the incident');

        $closed = $this->sos()->closeByReporter($acked, $customer);

        $this->assertSame('closed', $closed->state);
        $this->assertSame('reporter_acknowledged', $closed->closure_reason);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_T057_both_reporter_roles_can_raise_an_incident(): void
    {
        $customer = User::factory()->create();
        $professional = User::factory()->create();
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $a = $this->sos()->initiate('customer', $customer);
        $b = $this->sos()->initiate('professional', $professional, null, $profile->id);

        $this->assertSame('customer', $a->reporter_role);
        $this->assertSame('professional', $b->reporter_role);
        $this->assertSame(2, SosIncident::count());
        $this->assertNotSame($a->public_id, $b->public_id);
    }

    public function test_T058_no_false_delivery_claim_when_channel_reports_no_delivery(): void
    {
        $customer = User::factory()->create();

        $incident = $this->sos()->initiate('customer', $customer);
        // Attach a contact phone so the SMS path is attempted (logging adapter = not delivered).
        $incident->update(['contact_phone_ciphertext' => 'not-encrypted-number']);

        $result = $this->sos()->dispatchNotifications($incident->fresh());

        $smsAttempt = $incident->fresh()->deliveryAttempts()->where('channel', 'sms')->first();

        $this->assertNotNull($smsAttempt);
        $this->assertSame('failed', $smsAttempt->status, 'a log-only adapter must never report delivered');
        $this->assertFalse($result['sms_delivered'], 'the SMS channel must not claim delivery');
        // Ops intake is a separate, genuine delivery.
        $this->assertTrue($result['delivered']);
        $this->assertSame('notified_ops', $incident->fresh()->state);
    }

    public function test_T059_unacknowledged_incident_escalates_automatically(): void
    {
        $customer = User::factory()->create();
        $incident = $this->sos()->initiate('customer', $customer);

        // Not yet past the SLA.
        $this->assertFalse($this->sos()->escalateIfUnacknowledged($incident, 60));

        // Simulate the SLA elapsing (created_at is not mass-assignable).
        $incident->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->assertTrue($this->sos()->escalateIfUnacknowledged($incident->fresh(), 60));
        $this->assertSame(1, $incident->fresh()->escalations()->count());
        $this->assertSame('coordinating', $incident->fresh()->state);
    }

    public function test_acknowledged_incident_is_not_escalated(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();

        $incident = $this->sos()->initiate('customer', $customer);
        $this->sos()->acknowledge($incident, $staff);

        $incident->fresh()->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->assertFalse($this->sos()->escalateIfUnacknowledged($incident->fresh(), 60));
    }

    public function test_T060_senior_closure_requires_a_documented_reason(): void
    {
        $customer = User::factory()->create();
        $senior = User::factory()->create();

        $incident = $this->sos()->initiate('customer', $customer);

        $this->expectException(\DomainException::class);
        $this->sos()->closeBySeniorReview($incident, $senior, '');
    }

    public function test_T060_senior_closure_with_reason_is_recorded(): void
    {
        $customer = User::factory()->create();
        $senior = User::factory()->create();

        $incident = $this->sos()->initiate('customer', $customer);
        $closed = $this->sos()->closeBySeniorReview($incident, $senior, 'تعذر الوصول للمبلّغ بعد 3 محاولات موثقة');

        $this->assertSame('closed', $closed->state);
        $this->assertSame('senior_review', $closed->closure_reason);
        $this->assertNotNull($closed->closure_notes);
    }

    public function test_incidents_are_independent_of_each_other(): void
    {
        $a = $this->sos()->initiate('customer', User::factory()->create());
        $b = $this->sos()->initiate('customer', User::factory()->create());

        $this->sos()->acknowledge($a, User::factory()->create());

        $this->assertTrue($a->fresh()->isAcknowledged());
        $this->assertFalse($b->fresh()->isAcknowledged());
    }
}