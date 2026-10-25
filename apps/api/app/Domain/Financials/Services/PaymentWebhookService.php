<?php

namespace App\Domain\Financials\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Models\CashCollectionConfirmation;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\ProviderReceivable;
use Illuminate\Support\Facades\DB;

/**
 * Payment settlement.
 *
 * NON-NEGOTIABLE (T-045, T-046, T-047, AC-09):
 *  - only a signature-verified server webhook settles an electronic payment;
 *  - a replayed webhook posts exactly ONE settlement (idempotent by gateway event id);
 *  - a client claiming "paid" never settles anything;
 *  - cash settles only when BOTH parties attest; disagreement becomes a dispute.
 *
 * Accounting model (three balanced journals per settled visit):
 *   A. invoice finalized : Dr customer_receivable T | Cr provider_payable (T−C) + Cr commission_revenue C
 *   B. collected         : Dr (gateway_clearing | cash_collected_provider) T | Cr customer_receivable T
 *   C. provider earning  : Dr provider_payable (T−C) | Cr (gateway_clearing | cash_collected_provider) (T−C)
 *   → for cash, the provider physically retains (T−C) and holds C on the platform's behalf.
 */
class PaymentWebhookService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /** Invoice + commission are recognised the moment the invoice is finalized. */
    public function finalizeInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice->finalized_at !== null) {
                return;
            }

            $this->ledger->ensureAccounts();

            $total = (string) $invoice->total;
            $commission = (string) $invoice->commission_total;
            $net = bcsub($total, $commission, 2);

            $this->ledger->post(
                [
                    ['account' => LedgerAccount::CUSTOMER_RECEIVABLE, 'side' => 'debit', 'amount' => $total],
                    ['account' => LedgerAccount::PROVIDER_PAYABLE, 'side' => 'credit', 'amount' => $net],
                    ['account' => LedgerAccount::COMMISSION_REVENUE, 'side' => 'credit', 'amount' => $commission],
                ],
                'invoice_finalized',
                (string) $invoice->id,
                'Invoice finalized',
                createdBy: null,
            );

            $invoice->update(['state' => 'due', 'finalized_at' => now()]);
        });
    }

    /**
     * @return array{status: string, event_id: int|null, settled: bool, reason?: string}
     */
    public function handleWebhook(
        string $gateway,
        string $gatewayEventId,
        string $eventType,
        array $payload,
        bool $signatureValid,
    ): array {
        // 1) Persist the raw event with an idempotency key BEFORE any money movement.
        $existing = PaymentEvent::query()->where('gateway_event_id', $gatewayEventId)->first();

        if ($existing) {
            return ['status' => 'duplicate', 'event_id' => $existing->id, 'settled' => false];
        }

        $event = PaymentEvent::create([
            'gateway' => $gateway,
            'gateway_event_id' => $gatewayEventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature_valid' => $signatureValid,
            'received_at' => now(),
        ]);

        // 2) An unverified signature can never move money.
        if (! $signatureValid) {
            $this->audit->log('payment.webhook_signature_invalid', null, 'payment_event', $event->id, 'finance');

            return ['status' => 'rejected', 'event_id' => $event->id, 'settled' => false, 'reason' => 'invalid_signature'];
        }

        $intent = isset($payload['payment_intent_id'])
            ? PaymentIntent::query()->find($payload['payment_intent_id'])
            : null;

        if (! $intent) {
            return ['status' => 'unmatched', 'event_id' => $event->id, 'settled' => false];
        }

        $event->update(['payment_intent_id' => $intent->id]);

        if ($eventType !== 'payment.captured') {
            return ['status' => 'ignored', 'event_id' => $event->id, 'settled' => false];
        }

        $this->settleElectronic($intent);

        $event->update(['processed_at' => now()]);

        return ['status' => 'settled', 'event_id' => $event->id, 'settled' => true];
    }

    /** Journal B + C for a verified electronic capture. Idempotent per intent state. */
    public function settleElectronic(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent) {
            $intent = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();

            if ($intent->state === 'paid_verified') {
                return; // already settled
            }

            $invoice = Invoice::query()->whereKey($intent->invoice_id)->lockForUpdate()->firstOrFail();

            if ($invoice->finalized_at === null) {
                $this->finalizeInvoice($invoice);
                $invoice->refresh();
            }

            $this->ledger->ensureAccounts();

            $total = (string) $invoice->total;
            $net = bcsub($total, (string) $invoice->commission_total, 2);

            // B) collected
            $this->ledger->post(
                [
                    ['account' => LedgerAccount::GATEWAY_CLEARING, 'side' => 'debit', 'amount' => $total],
                    ['account' => LedgerAccount::CUSTOMER_RECEIVABLE, 'side' => 'credit', 'amount' => $total],
                ],
                'payment_collected',
                (string) $invoice->id,
                'Electronic capture verified by gateway',
            );

            // C) provider earning is now payable
            $this->ledger->post(
                [
                    ['account' => LedgerAccount::PROVIDER_PAYABLE, 'side' => 'debit', 'amount' => $net],
                    ['account' => LedgerAccount::GATEWAY_CLEARING, 'side' => 'credit', 'amount' => $net],
                ],
                'provider_earning',
                (string) $invoice->id,
                'Provider payable recognised (net of commission)',
            );

            $intent->update(['state' => 'paid_verified']);
            $invoice->update(['state' => 'paid_verified', 'settled_at' => now()]);

            $this->audit->log('payment.settled_electronic', null, 'payment_intent', $intent->public_id, 'finance');
        });
    }

    /**
     * Cash attestation by each party independently. Mismatch → dispute, never settlement.
     *
     * @return array{state: string}
     */
    public function confirmCash(Invoice $invoice, string $party, string $claim, int $userId, ?string $amount = null): array
    {
        CashCollectionConfirmation::updateOrCreate(
            ['invoice_id' => $invoice->id, 'party' => $party],
            ['claim' => $claim, 'amount' => $amount, 'confirmed_by' => $userId, 'confirmed_at' => now()],
        );

        $confirmations = CashCollectionConfirmation::query()
            ->where('invoice_id', $invoice->id)
            ->get()
            ->keyBy('party');

        $provider = $confirmations->get('provider');
        $customer = $confirmations->get('customer');

        if ($provider && $customer && $provider->claim === 'paid' && $customer->claim === 'paid') {
            $this->settleCash($invoice);

            return ['state' => 'cash_confirmed'];
        }

        if ($provider && $customer && $provider->claim !== $customer->claim) {
            $invoice->update(['state' => 'disputed']);

            $this->audit->log('payment.cash_mismatch', null, 'invoice', $invoice->public_id, 'finance');

            return ['state' => 'disputed'];
        }

        $invoice->update(['state' => 'cash_claimed']);

        return ['state' => 'cash_claimed'];
    }

    private function settleCash(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->state === 'cash_confirmed') {
                return;
            }

            if ($invoice->finalized_at === null) {
                $this->finalizeInvoice($invoice);
                $invoice->refresh();
            }

            $this->ledger->ensureAccounts();

            $total = (string) $invoice->total;
            $net = bcsub($total, (string) $invoice->commission_total, 2);

            // B) the provider collected the full amount in cash
            $this->ledger->post(
                [
                    ['account' => LedgerAccount::CASH_COLLECTED, 'side' => 'debit', 'amount' => $total],
                    ['account' => LedgerAccount::CUSTOMER_RECEIVABLE, 'side' => 'credit', 'amount' => $total],
                ],
                'cash_collected',
                (string) $invoice->id,
                'Cash collected by provider (both parties attested)',
            );

            // C) the provider retains their net earning out of the cash they hold
            $this->ledger->post(
                [
                    ['account' => LedgerAccount::PROVIDER_PAYABLE, 'side' => 'debit', 'amount' => $net],
                    ['account' => LedgerAccount::CASH_COLLECTED, 'side' => 'credit', 'amount' => $net],
                ],
                'provider_earning',
                (string) $invoice->id,
                'Provider net earning retained from collected cash',
            );

            // The remaining debit balance on cash_collected equals the commission the
            // provider now owes the platform — tracked as a collectible receivable.
            $commission = (string) $invoice->commission_total;

            if (bccomp($commission, '0.00', 2) > 0) {
                ProviderReceivable::create([
                    'professional_profile_id' => $invoice->visit?->professional_profile_id,
                    'amount' => $commission,
                    'kind' => 'cash_commission',
                    'status' => 'open',
                    'reference_type' => 'invoice',
                    'reference_id' => (string) $invoice->id,
                ]);
            }

            $invoice->update(['state' => 'cash_confirmed', 'settled_at' => now()]);

            $this->audit->log('payment.settled_cash', null, 'invoice', $invoice->public_id, 'finance', [
                'commission_due' => $commission,
                'provider_net' => $net,
            ]);
        });
    }
}