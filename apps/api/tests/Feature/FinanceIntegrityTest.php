<?php

namespace Tests\Feature;

use App\Domain\Financials\Services\LedgerService;
use App\Domain\Financials\Services\PaymentWebhookService;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LedgerAccount;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-045 (replayed webhook posts ONE settlement), T-046 (forged/unverified payload
 * never settles), T-047 (cash disagreement → dispute, not settlement),
 * T-048 (journals always balance).
 */
class FinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function payments(): PaymentWebhookService
    {
        return app(PaymentWebhookService::class);
    }

    private function invoice(string $total = '500.00', string $commission = '75.00'): Invoice
    {
        $customer = User::factory()->create();
        $profile = \App\Models\ProfessionalProfile::factory()->create();

        $booking = \App\Models\Booking::factory()->create([
            'customer_user_id' => $customer->id,
            'professional_profile_id' => $profile->id,
            'state' => 'provider_completed',
        ]);

        $visit = \App\Models\Visit::create([
            'booking_id' => $booking->id,
            'professional_profile_id' => $profile->id,
            'state' => 'provider_completed',
        ]);

        $invoice = Invoice::create([
            'visit_id' => $visit->id,
            'booking_id' => $booking->id,
            'customer_user_id' => $customer->id,
            'state' => 'draft_invoice',
            'subtotal' => $total,
            'total' => $total,
            'commission_total' => $commission,
            'currency' => 'EGP',
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'kind' => 'procedure',
            'description_ar' => 'إجراء تمريضي',
            'description_en' => 'Nursing procedure',
            'quantity' => 1,
            'unit_price' => $total,
            'amount' => $total,
            'commission_amount' => $commission,
        ]);

        return $invoice;
    }

    public function test_T048_ledger_rejects_unbalanced_journal(): void
    {
        $this->ledger()->ensureAccounts();

        $this->expectException(\DomainException::class);

        $this->ledger()->post(
            [
                ['account' => LedgerAccount::CUSTOMER_RECEIVABLE, 'side' => 'debit', 'amount' => '100.00'],
                ['account' => LedgerAccount::COMMISSION_REVENUE, 'side' => 'credit', 'amount' => '90.00'],
            ],
            'test',
            '1',
        );
    }

    public function test_T048_balanced_journal_posts_and_verifies(): void
    {
        $this->ledger()->ensureAccounts();

        $journal = $this->ledger()->post(
            [
                ['account' => LedgerAccount::CUSTOMER_RECEIVABLE, 'side' => 'debit', 'amount' => '100.00'],
                ['account' => LedgerAccount::COMMISSION_REVENUE, 'side' => 'credit', 'amount' => '100.00'],
            ],
            'test',
            '1',
        );

        $balance = $this->ledger()->journalBalance($journal);

        $this->assertTrue($balance['balanced']);
        $this->assertSame('100.00', $balance['debits']);
        $this->assertSame('100.00', $balance['credits']);
    }

    public function test_T045_replayed_webhook_posts_exactly_one_settlement(): void
    {
        $invoice = $this->invoice();
        $this->payments()->finalizeInvoice($invoice);

        $intent = PaymentIntent::create([
            'invoice_id' => $invoice->id,
            'gateway' => 'mock',
            'method' => 'card',
            'amount' => '500.00',
            'currency' => 'EGP',
            'state' => 'created',
        ]);

        $payload = ['payment_intent_id' => $intent->id, 'amount' => '500.00'];

        $first = $this->payments()->handleWebhook('mock', 'evt_123', 'payment.captured', $payload, true);
        $this->assertSame('settled', $first['status']);
        $this->assertTrue($first['settled']);

        // Replay the identical gateway event 9 more times.
        for ($i = 0; $i < 9; $i++) {
            $replay = $this->payments()->handleWebhook('mock', 'evt_123', 'payment.captured', $payload, true);
            $this->assertSame('duplicate', $replay['status']);
            $this->assertFalse($replay['settled']);
        }

        $this->assertSame(1, \App\Models\PaymentEvent::query()->where('gateway_event_id', 'evt_123')->count());
        $this->assertSame('paid_verified', $invoice->fresh()->state);

        // Ledger is balanced overall and settled exactly once (one journal, two lines).
        $this->assertTrue($this->ledger()->wholeLedgerBalance()['balanced']);
        $this->assertSame(
            1,
            \App\Models\LedgerEntry::query()
                ->where('reference_type', 'payment_collected')
                ->distinct()
                ->count('journal_id'),
        );
    }

    public function test_T046_invalid_signature_never_settles(): void
    {
        $invoice = $this->invoice();
        $this->payments()->finalizeInvoice($invoice);

        $intent = PaymentIntent::create([
            'invoice_id' => $invoice->id,
            'gateway' => 'mock',
            'method' => 'card',
            'amount' => '500.00',
            'currency' => 'EGP',
            'state' => 'created',
        ]);

        $result = $this->payments()->handleWebhook(
            'mock',
            'evt_forged',
            'payment.captured',
            ['payment_intent_id' => $intent->id, 'amount' => '500.00'],
            false, // signature invalid
        );

        $this->assertSame('rejected', $result['status']);
        $this->assertFalse($result['settled']);
        $this->assertSame('due', $invoice->fresh()->state);
        $this->assertSame(0, \App\Models\LedgerEntry::query()->where('reference_type', 'payment_collected')->count());
    }

    public function test_T047_cash_disagreement_becomes_dispute_not_settlement(): void
    {
        $invoice = $this->invoice();
        $this->payments()->finalizeInvoice($invoice);

        $provider = User::factory()->create();
        $customer = User::factory()->create();

        $a = $this->payments()->confirmCash($invoice, 'provider', 'paid', $provider->id, '500.00');
        $this->assertSame('cash_claimed', $a['state']);

        // Customer denies → dispute, never "paid".
        $b = $this->payments()->confirmCash($invoice->fresh(), 'customer', 'not_paid', $customer->id);
        $this->assertSame('disputed', $b['state']);
        $this->assertSame('disputed', $invoice->fresh()->state);
        $this->assertSame(0, \App\Models\LedgerEntry::query()->where('reference_type', 'cash_collected')->count());
    }

    public function test_cash_settles_only_when_both_parties_attest_and_records_commission_receivable(): void
    {
        $invoice = $this->invoice('500.00', '75.00');
        $this->payments()->finalizeInvoice($invoice);

        $provider = User::factory()->create();
        $customer = User::factory()->create();

        $this->payments()->confirmCash($invoice, 'provider', 'paid', $provider->id, '500.00');
        $result = $this->payments()->confirmCash($invoice->fresh(), 'customer', 'paid', $customer->id, '500.00');

        $this->assertSame('cash_confirmed', $result['state']);

        $fresh = $invoice->fresh();
        $this->assertSame('cash_confirmed', $fresh->state);
        $this->assertNotNull($fresh->settled_at);

        // Provider owes the commission.
        $this->assertDatabaseHas('provider_receivables', [
            'kind' => 'cash_commission',
            'amount' => 75.00,
            'status' => 'open',
        ]);

        $this->assertTrue($this->ledger()->wholeLedgerBalance()['balanced']);
    }
}