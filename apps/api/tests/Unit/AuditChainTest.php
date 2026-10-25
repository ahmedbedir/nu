<?php

namespace Tests\Unit;

use App\Domain\Compliance\Services\AuditLogger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_events_form_a_verifiable_hash_chain(): void
    {
        $logger = app(AuditLogger::class);
        $user = User::factory()->create();

        $logger->log('test.first', $user, 'patient', 'abc');
        $logger->log('test.second', $user, 'patient', 'abc', 'clinical', ['x' => 1]);
        $logger->log('test.third', null, 'system', null);

        $this->assertTrue($logger->verifyChain());
        $this->assertDatabaseCount('audit_events', 3);
    }

    public function test_tampering_breaks_the_chain_detection(): void
    {
        $logger = app(AuditLogger::class);
        $logger->log('test.one');
        $logger->log('test.two');

        $this->assertTrue($logger->verifyChain());

        // Simulate casual tampering of a stored row.
        \App\Models\AuditEvent::query()->first()->forceFill(['event' => 'test.tampered'])->save();

        $this->assertFalse($logger->verifyChain());
    }
}