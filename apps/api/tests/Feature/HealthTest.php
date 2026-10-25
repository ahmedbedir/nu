<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_real_database_connection(): void
    {
        $res = $this->getJson('/api/v1/health');

        $res->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.connected', true);

        // It must be the isolated test database, never `nu`.
        $this->assertSame('nu_test', $res->json('checks.database.database'));
    }

    public function test_ping_requires_authentication(): void
    {
        $this->getJson('/api/v1/ping')->assertStatus(401);
    }
}