<?php

namespace App\Domain\Compliance\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Append-only audit trail with a hash chain (#215).
 *
 * Every consequential action (identity, consent, clinical access, finance, SOS,
 * admin settings) must go through here. Rows are never mutated by application code.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata  Must never contain PHI/PII (phones, national IDs, notes).
     */
    public function log(
        string $event,
        ?User $actor = null,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        ?string $purpose = null,
        array $metadata = [],
        ?string $actorRole = null,
        ?string $correlationId = null,
    ): AuditEvent {
        $previous = AuditEvent::query()->orderByDesc('id')->first();
        $prevHash = $previous?->hash;

        // Normalise everything that goes into the hash so verification is deterministic.
        $subjectId = $subjectId !== null ? (string) $subjectId : null;
        $occurredAt = now()->startOfSecond();

        $payload = $this->payload($event, $actor?->id, $subjectType, $subjectId, $purpose, $metadata, $occurredAt);
        $hash = hash('sha256', ($prevHash ?? '').$payload);

        try {
            $row = new AuditEvent([
                'event' => $event,
                'actor_user_id' => $actor?->id,
                'actor_role' => $actorRole,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'purpose' => $purpose,
                'metadata' => $metadata,
                'correlation_id' => $correlationId,
                'ip' => request()->ip(),
                'prev_hash' => $prevHash,
                'hash' => $hash,
            ]);
            $row->created_at = $occurredAt;
            $row->save();

            return $row;
        } catch (\Throwable $e) {
            // An audit write failure must be loud, never silent.
            Log::error('audit.write_failed', ['event' => $event, 'error' => $e->getMessage()]);

            if (app()->environment('local', 'testing')) {
                throw $e;
            }

            return new AuditEvent(['event' => $event]);
        }
    }

    /** Verify the chain is intact (tamper detection). */
    public function verifyChain(): bool
    {
        $prev = null;

        foreach (AuditEvent::query()->orderBy('id')->cursor() as $row) {
            $payload = $this->payload(
                $row->event,
                $row->actor_user_id,
                $row->subject_type,
                $row->subject_id,
                $row->purpose,
                $row->metadata ?? [],
                $row->created_at,
            );

            $expected = hash('sha256', ($prev ?? '').$payload);

            if (! hash_equals($row->hash ?? '', $expected)) {
                return false;
            }

            $prev = $row->hash;
        }

        return true;
    }

    private function payload(
        string $event,
        ?int $actorId,
        ?string $subjectType,
        ?string $subjectId,
        ?string $purpose,
        array $metadata,
        \DateTimeInterface $at,
    ): string {
        return json_encode([
            'event' => $event,
            'actor' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'purpose' => $purpose,
            'metadata' => $metadata,
            'at' => $at->format('Y-m-d\TH:i:sP'),
        ], JSON_THROW_ON_ERROR);
    }
}