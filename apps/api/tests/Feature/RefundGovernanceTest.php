<?php

namespace Tests\Feature;

use App\Domain\Financials\Services\LedgerService;
use App\Domain\Financials\Services\PaymentWebhookService;
use App\Domain\Financials\Services\RefundService;
use App\Models\Consent;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\PaymentIntent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-054 (credit refund needs consent), T-055 (self-approval blocked, dual approval
 * above the threshold), AC-09 (never refund more than collected).
 */
class RefundGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(string $code = 'manager_a'): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'code' => 'fin_'.$code,
            'name_ar' => 'مدير مالي',
            'name_en' => 'Finance manager',
            'scope' => 'staff',
            'is_system' => false,
        ]);
        $permission = Permission::firstOrCreate(
            ['code' => 'finance.approve_refund'],
            ['name_ar' => 'اعتماد استرداد', 'name_en' => 'Approve refund', 'group' => 'finance'],
        );
        $role->permissions()->sync([$permission->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function paidInvoice(string $total = '500.00', string $commission = '75.00'): Invoice
    {
        $customer = User::factory()->create();
        $invoice = Invoice::create([
            'customer_user_id' => $customer->id,
            'state' => 'draft_invoice',
            'subtotal' => $total,
            'total' => $total,
            'commission_total' => $commission,
        ]);

        $payments = app(PaymentWebhookService::class);
        $payments->finalizeInvoice($invoice);

        $intent = PaymentIntent::create([
            'invoice_id' => $invoice->id,
            'gateway' => 'mock',
            'method' => 'card',
            'amount' => $total,
            'currency' => 'EGP',
            'state' => 'created',
        ]);

        $payments->handleWebhook('mock', 'evt_'.$invoice->id, 'payment.captured', [
            'payment_intent_id' => $intent->id,
        ], true);

        return $invoice->fresh();
    }

    public function test_refund_cannot_exceed_the_collected_amount(): void
    {
        $invoice = $this->paidInvoice('500.00');
        $requester = User::factory()->create();

        $this->expectException(\DomainException::class);
        app(RefundService::class)->request($invoice, '600.00', 'original', 'over', $requester);
    }

    public function test_credit_refund_requires_explicit_customer_consent(): void
    {
        $invoice = $this->paidInvoice('200.00');
        $requester = User::factory()->create();

        $this->expectException(\DomainException::class);
        app(RefundService::class)->request($invoice, '100.00', 'credit', 'goodwill', $requester);
    }

    public function test_credit_refund_succeeds_with_recorded_consent(): void
    {
        $invoice = $this->paidInvoice('200.00');
        $requester = User::factory()->create();

        Consent::create([
            'user_id' => $invoice->customer_user_id,
            'purpose' => 'refund_credit',
            'version' => 'v1',
            'granted' => true,
            'granted_at' => now(),
        ]);

        $refund = app(RefundService::class)->request($invoice, '100.00', 'credit', 'goodwill', $requester);

        $this->assertSame('requested', $refund->state);
        $this->assertSame('100.00', (string) $refund->amount);
    }

    public function test_requester_cannot_approve_their_own_refund(): void
    {
        $invoice = $this->paidInvoice('100.00');
        $manager = $this->manager();

        $refund = app(RefundService::class)->request($invoice, '50.00', 'original', 'issue', $manager);

        $this->expectException(\DomainException::class);
        app(RefundService::class)->approve($refund, $manager);
    }

    public function test_large_refund_needs_a_second_different_approver(): void
    {
        $invoice = $this->paidInvoice('800.00');
        $requester = User::factory()->create();
        $manager = $this->manager('m1');

        $refund = app(RefundService::class)->request($invoice, '700.00', 'original', 'big issue', $requester);

        try {
            app(RefundService::class)->approve($refund, $manager);
            $this->fail('dual approval should have been required');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('موافقة ثانية', $e->getMessage());
        }

        $second = $this->manager('m2');
        $approved = app(RefundService::class)->approve($refund, $manager, $second);

        $this->assertSame('processed', $approved->state);
    }

    public function test_approved_refund_posts_a_balanced_compensating_journal(): void
    {
        $invoice = $this->paidInvoice('300.00');
        $requester = User::factory()->create();
        $manager = $this->manager();

        $refund = app(RefundService::class)->request($invoice, '120.00', 'original', 'partial', $requester);
        app(RefundService::class)->approve($refund, $manager);

        $ledger = app(LedgerService::class);
        $this->assertTrue($ledger->wholeLedgerBalance()['balanced']);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => 'refund',
            'account_id' => LedgerAccount::query()->where('code', LedgerAccount::REFUNDS)->value('id'),
            'side' => 'debit',
        ]);
    }
}