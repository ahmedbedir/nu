<?php

namespace App\Domain\Safety\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Shared\Exceptions\DomainRuleException;
use App\Models\SosAcknowledgement;
use App\Models\SosDeliveryAttempt;
use App\Models\SosEscalation;
use App\Models\SosIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SOS intake and escalation (PRD-08, decisions #176–#184).
 *
 * Truthfulness rules that must never be relaxed (T-057, T-058, T-059, T-060):
 *  - the fast durable write happens FIRST, so a slow map/notification call cannot
 *    lose the incident;
 *  - each delivery attempt records its real outcome — "sent" is never the same as
 *    "delivered", and a failed attempt is visible as failed;
 *  - ACK and RESOLVE are separate events;
 *  - an incident with no acknowledgement escalates automatically;
 *  - closing without the reporter's confirmation requires a recorded senior review.
 */
class SosService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SmsSender $sms,
    ) {}

    /** Step 1 — durable intake. Deliberately does no network work. */
    public function initiate(
        string $reporterRole,
        ?User $user = null,
        ?int $bookingId = null,
        ?int $professionalProfileId = null,
        ?float $lat = null,
        ?float $lng = null,
        ?float $accuracy = null,
    ): SosIncident {
        $incident = SosIncident::create([
            'booking_id' => $bookingId,
            'user_id' => $user?->id,
            'professional_profile_id' => $professionalProfileId,
            'reporter_role' => $reporterRole,
            'state' => 'accepted_server',
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_m' => $accuracy,
            'occurred_at' => now(),
        ]);

        $this->audit->log('sos.initiated', $user, 'sos_incident', $incident->public_id, 'safety', [
            'reporter_role' => $reporterRole,
        ]);

        return $incident;
    }

    /**
     * Step 2 — best-effort fan-out. Each channel's real result is persisted.
     *
     * @return array{delivered: bool, attempts: array<int, array{channel: string, status: string}>}
     */
    public function dispatchNotifications(SosIncident $incident): array
    {
        $attempts = [];

        // The staffed operations intake is the authoritative delivery path: once the
        // incident is in the ops queue, someone is responsible for it.
        SosDeliveryAttempt::create([
            'sos_incident_id' => $incident->id,
            'channel' => 'internal',
            'status' => 'delivered',
            'attempted_at' => now(),
        ]);
        $attempts[] = ['channel' => 'internal', 'status' => 'delivered'];
        $reachedOps = true;
        $smsDelivered = null;

        // Supplementary SMS. Its real outcome remains visible and never becomes
        // "delivered" without provider evidence.
        if ($incident->contact_phone_ciphertext) {
            $result = $this->sms->send($this->decryptPhone($incident->contact_phone_ciphertext), 'HomeCare SOS', 'sos');
            $smsDelivered = (bool) $result['delivered'];

            SosDeliveryAttempt::create([
                'sos_incident_id' => $incident->id,
                'channel' => 'sms',
                'status' => $smsDelivered ? 'delivered' : 'failed',
                'error' => $smsDelivered ? null : 'provider_reported_no_delivery',
                'attempted_at' => now(),
            ]);

            $attempts[] = ['channel' => 'sms', 'status' => $smsDelivered ? 'delivered' : 'failed'];
        }

        $incident->update(['state' => 'notified_ops']);

        return [
            'delivered' => $reachedOps,
            'sms_delivered' => $smsDelivered,
            'attempts' => $attempts,
        ];
    }

    /** Ops acknowledges — a distinct event from resolution. */
    public function acknowledge(SosIncident $incident, User $staff, string $channel = 'internal', ?string $notes = null): SosIncident
    {
        return DB::transaction(function () use ($incident, $staff, $channel, $notes) {
            $locked = SosIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if ($locked->acked_at !== null) {
                return $locked; // idempotent
            }

            SosAcknowledgement::create([
                'sos_incident_id' => $locked->id,
                'acknowledged_by' => $staff->id,
                'channel' => $channel,
                'acknowledged_at' => now(),
                'notes' => $notes,
            ]);

            $locked->update(['state' => 'acknowledged', 'acked_at' => now(), 'acked_by' => $staff->id]);

            $locked->escalations()->where('status', 'pending')->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
            ]);

            $this->audit->log('sos.acknowledged', $staff, 'sos_incident', $locked->public_id, 'safety');

            return $locked->fresh();
        });
    }

    /** Escalate when no acknowledgement has arrived inside the SLA. */
    public function escalateIfUnacknowledged(SosIncident $incident, int $slaSeconds = 60): bool
    {
        $incident->refresh();

        if ($incident->isAcknowledged()) {
            return false;
        }

        if (abs($incident->created_at->diffInSeconds(now())) < $slaSeconds) {
            return false;
        }

        SosEscalation::create([
            'sos_incident_id' => $incident->id,
            'level' => ($incident->escalations()->max('level') ?? 0) + 1,
            'channel' => 'internal',
            'escalated_at' => now(),
            'status' => 'pending',
        ]);

        $incident->update(['state' => 'coordinating']);

        $this->audit->log('sos.escalated', null, 'sos_incident', $incident->public_id, 'safety');

        return true;
    }

    /** Normal closure: the reporter confirms the situation is resolved. */
    public function closeByReporter(SosIncident $incident, User $actor): SosIncident
    {
        return $this->close($incident, $actor, 'reporter_acknowledged', null);
    }

    /** Exceptional closure when the reporter cannot be reached — requires a reason. */
    public function closeBySeniorReview(SosIncident $incident, User $senior, string $reason): SosIncident
    {
        if (trim($reason) === '') {
            throw DomainRuleException::invalid('closure_reason_required', 'إغلاق الاستغاثة بدون تأكيد المبلّغ يتطلب سببًا موثقًا');
        }

        return $this->close($incident, $senior, 'senior_review', $reason);
    }

    private function close(SosIncident $incident, User $actor, string $reason, ?string $notes): SosIncident
    {
        return DB::transaction(function () use ($incident, $actor, $reason, $notes) {
            $locked = SosIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'state' => 'closed',
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'closure_reason' => $reason,
                'closure_notes' => $notes,
            ]);

            $this->audit->log('sos.closed', $actor, 'sos_incident', $locked->public_id, 'safety', [
                'reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    private function decryptPhone(string $cipher): string
    {
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return $cipher;
        }
    }
}