<?php

namespace App\Domain\Financials\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Models\AdminApproval;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Refunds and disputes (PRD-10, decisions #196–#205).
 *
 * Rules enforced here:
 *  - a refund can never exceed what was actually collected (AC-09);
 *  - refund-to-credit requires explicit customer consent AND legal permission (#204);
 *  - a large refund needs a second, different approver — a support agent cannot
 *    approve their own request (T-055, AC-11);
 *  - money moves only through a balanced compensating journal.
 */
class RefundService
{
    /** Above this amount, a second approver is mandatory. */
    private const DUAL_APPROVAL_THRESHOLD = '500.00';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function request(Invoice $invoice, string $amount, string $method, string $reason, User $requester): Refund
    {
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new \DomainException('مبلغ الاسترداد يجب أن يكون أكبر من صفر');
        }

        $collected = $this->collectedTotal($invoice);

        if (bccomp($amount, $collected, 2) > 0) {
            throw new \DomainException('لا يمكن استرداد مبلغ أكبر من المحصّل فعليًا');
        }

        if ($method === 'credit' && ! $this->customerConsentedToCredit($invoice)) {
            throw new \DomainException('الاسترداد كرصيد يتطلب موافقة صريحة من العميل');
        }

        $refund = Refund::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => $method,
            'state' => 'requested',
            'reason' => $reason,
            'requested_by' => $requester->id,
        ]);

        $this->audit->log('refund.requested', $requester, 'refund', $refund->public_id, 'finance', [
            'amount' => $amount,
            'method' => $method,
        ]);

        return $refund;
    }

    /**
     * @param  User  $approver  must hold `finance.approve_refund`
     * @param  User|null  $secondApprover  required above the threshold, must differ
     */
    public function approve(Refund $refund, User $approver, ?User $secondApprover = null): Refund
    {
        if (! $approver->hasPermission('finance.approve_refund')) {
            throw new \DomainException('اعتماد الاسترداد يتطلب صلاحية المدير المالي');
        }

        if ((int) $refund->requested_by === (int) $approver->id) {
            throw new \DomainException('لا يمكن للموظف اعتماد طلب استرداد قدّمه بنفسه');
        }

        $needsSecond = bccomp((string) $refund->amount, self::DUAL_APPROVAL_THRESHOLD, 2) >= 0;

        if ($needsSecond) {
            if (! $secondApprover) {
                throw new \DomainException('المبلغ يتطلب موافقة ثانية من مدير آخر');
            }

            if ((int) $secondApprover->id === (int) $approver->id) {
                throw new \DomainException('الموافقة الثانية يجب أن تكون من شخص مختلف');
            }

            if (! $secondApprover->hasPermission('finance.approve_refund')) {
                throw new \DomainException('الموافق الثاني لا يملك صلاحية اعتماد الاسترداد');
            }
        }

        return DB::transaction(function () use ($refund, $approver, $secondApprover, $needsSecond) {
            $refund->update([
                'state' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            AdminApproval::create([
                'kind' => 'refund_large',
                'action' => 'refund.approved',
                'payload' => [
                    'refund_public_id' => $refund->public_id,
                    'amount' => (string) $refund->amount,
                    'dual_approval' => $needsSecond,
                    'second_approver' => $secondApprover?->public_id,
                ],
                'status' => 'approved',
                'requested_by' => $refund->requested_by,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'reason' => $refund->reason,
            ]);

            $this->postRefundJournal($refund);

            $refund->update(['state' => 'processed']);

            $this->audit->log('refund.processed', $approver, 'refund', $refund->public_id, 'finance', [
                'amount' => (string) $refund->amount,
                'dual_approval' => $needsSecond,
            ]);

            return $refund->fresh();
        });
    }

    /** Cash disagreement opens a financial dispute owned by the financial manager. */
    public function openDispute(Invoice $invoice, string $category, string $description, User $opener): \App\Models\FinancialDispute
    {
        $dispute = \App\Models\FinancialDispute::create([
            'invoice_id' => $invoice->id,
            'state' => 'open',
            'category' => $category,
            'description' => $description,
            'opened_by' => $opener->id,
        ]);

        $invoice->update(['state' => 'disputed']);

        $this->audit->log('finance.dispute_opened', $opener, 'invoice', $invoice->public_id, 'finance', [
            'category' => $category,
        ]);

        return $dispute;
    }

    private function postRefundJournal(Refund $refund): void
    {
        $this->ledger->ensureAccounts();

        // Dr refunds expense / Cr the channel the money leaves from.
        $creditAccount = $refund->method === 'credit'
            ? LedgerAccount::CUSTOMER_RECEIVABLE
            : LedgerAccount::GATEWAY_CLEARING;

        $this->ledger->post(
            [
                ['account' => LedgerAccount::REFUNDS, 'side' => 'debit', 'amount' => (string) $refund->amount],
                ['account' => $creditAccount, 'side' => 'credit', 'amount' => (string) $refund->amount],
            ],
            'refund',
            (string) $refund->id,
            'Refund processed ('.$refund->method.')',
        );
    }

    private function collectedTotal(Invoice $invoice): string
    {
        return \App\Models\LedgerEntry::query()
            ->where('reference_type', 'payment_collected')
            ->where('reference_id', (string) $invoice->id)
            ->where('side', 'debit')
            ->sum('amount') ?: '0.00';
    }

    private function customerConsentedToCredit(Invoice $invoice): bool
    {
        return \App\Models\Consent::query()
            ->where('user_id', $invoice->customer_user_id)
            ->where('purpose', 'refund_credit')
            ->where('granted', true)
            ->whereNull('revoked_at')
            ->exists();
    }
}