<?php

namespace Tests\Feature;

use App\Models\SosIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SosHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_raise_sos_and_response_is_honest_about_delivery(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $res = $this->postJson('/api/v1/sos', [
            'reporter_role' => 'customer',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'accuracy_m' => 12,
            'contact_phone' => '01012345678',
        ])->assertCreated();

        $this->assertNotEmpty($res->json('incident.public_id'));
        // Durable intake happened first, then ops intake succeeded.
        $this->assertSame('notified_ops', $res->json('incident.state'));
        $this->assertNull($res->json('incident.acked_at'), 'notified is not the same as acknowledged');
        // Ops intake is real; the local SMS adapter never claims delivery.
        $this->assertTrue($res->json('notified'));
        $this->assertFalse($res->json('sms_delivered'));
        $this->assertSame('delivered', $res->json('channels.0.status'));
        $this->assertSame('failed', $res->json('channels.1.status'));
    }

    public function test_unrelated_user_cannot_close_another_reporters_sos(): void
    {
        $reporter = User::factory()->create();
        $stranger = User::factory()->create();
        $incident = SosIncident::create([
            'user_id' => $reporter->id,
            'reporter_role' => 'customer',
            'state' => 'accepted_server',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson('/api/v1/sos/'.$incident->public_id.'/close', [
            'reason' => 'reporter_acknowledged',
        ])->assertStatus(403);
    }

    public function test_sos_ack_requires_explicit_permission(): void
    {
        $reporter = User::factory()->create();
        $incident = SosIncident::create([
            'user_id' => $reporter->id,
            'reporter_role' => 'customer',
            'state' => 'accepted_server',
            'occurred_at' => now(),
        ]);
        $ordinary = User::factory()->create();

        Sanctum::actingAs($ordinary);
        $this->postJson('/api/v1/sos/'.$incident->public_id.'/ack')->assertStatus(403);
    }
}