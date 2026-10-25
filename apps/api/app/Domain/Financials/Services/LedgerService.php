<?php

namespace App\Domain\Financials\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Double-entry ledger (PRD-09, #190–#193).
 *
 * Every business event posts ONE balanced journal: the sum of debits equals the sum
 * of credits, in the same currency. Entries are append-only; a correction is a new
 * compensating journal. Amounts are DECIMAL(18,2) EGP — never floating point.
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: string, side: string, amount: string|float|int}>  $lines
     *
     * @throws \DomainException when the journal does not balance
     */
    public function post(
        array $lines,
        string $referenceType,
        string $referenceId,
        ?string $memo = null,
        ?string $currency = 'EGP',
        ?int $createdBy = null,
        ?string $journalId = null,
    ): string {
        $journalId ??= (string) Str::ulid();

        $debits = '0.00';
        $credits = '0.00';

        foreach ($lines as $line) {
            $amount = $this->normalise($line['amount']);

            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new \DomainException('Ledger amounts must be positive');
            }

            if ($line['side'] === 'debit') {
                $debits = bcadd($debits, $amount, 2);
            } elseif ($line['side'] === 'credit') {
                $credits = bcadd($credits, $amount, 2);
            } else {
                throw new \DomainException('Unknown ledger side: '.$line['side']);
            }
        }

        if (bccomp($debits, $credits, 2) !== 0) {
            throw new \DomainException("Unbalanced journal: debit {$debits} ≠ credit {$credits}");
        }

        return DB::transaction(function () use ($lines, $journalId, $referenceType, $referenceId, $memo, $currency, $createdBy) {
            foreach ($lines as $line) {
                $account = LedgerAccount::query()->where('code', $line['account'])->firstOrFail();

                LedgerEntry::create([
                    'journal_id' => $journalId,
                    'account_id' => $account->id,
                    'side' => $line['side'],
                    'amount' => $this->normalise($line['amount']),
                    'currency' => $line['currency'] ?? $currency,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'memo' => $memo,
                    'posted_at' => now(),
                    'created_by' => $createdBy,
                ]);
            }

            return $journalId;
        });
    }

    /** @return array{debits: string, credits: string, balanced: bool} */
    public function journalBalance(string $journalId): array
    {
        $rows = LedgerEntry::query()
            ->where('journal_id', $journalId)
            ->selectRaw("side, currency, SUM(amount) as total")
            ->groupBy('side', 'currency')
            ->get();

        $debits = '0.00';
        $credits = '0.00';

        foreach ($rows as $row) {
            if ($row->side === 'debit') {
                $debits = bcadd($debits, (string) $row->total, 2);
            } else {
                $credits = bcadd($credits, (string) $row->total, 2);
            }
        }

        return ['debits' => $debits, 'credits' => $credits, 'balanced' => bccomp($debits, $credits, 2) === 0];
    }

    /** @return array{debits: string, credits: string, balanced: bool} */
    public function wholeLedgerBalance(): array
    {
        $rows = LedgerEntry::query()
            ->selectRaw('side, SUM(amount) as total')
            ->groupBy('side')
            ->get();

        $debits = '0.00';
        $credits = '0.00';

        foreach ($rows as $row) {
            if ($row->side === 'debit') {
                $debits = bcadd($debits, (string) $row->total, 2);
            } else {
                $credits = bcadd($credits, (string) $row->total, 2);
            }
        }

        return ['debits' => $debits, 'credits' => $credits, 'balanced' => bccomp($debits, $credits, 2) === 0];
    }

    /** Upsert the canonical account list idempotently. */
    public function ensureAccounts(): void
    {
        $accounts = [
            [LedgerAccount::CUSTOMER_RECEIVABLE, 'مدفوعات مستحقة على العملاء', 'Customer receivable', 'asset'],
            [LedgerAccount::GATEWAY_CLEARING, 'تسويات بوابة الدفع', 'Gateway clearing', 'asset'],
            [LedgerAccount::CASH_COLLECTED, 'كاش محصّل بواسطة الممارس', 'Cash collected by provider', 'asset'],
            [LedgerAccount::COMMISSION_REVENUE, 'إيراد عمولة المنصة', 'Platform commission revenue', 'revenue'],
            [LedgerAccount::PROVIDER_PAYABLE, 'مستحقات الممارسين', 'Provider payable', 'liability'],
            [LedgerAccount::REFUNDS, 'المبالغ المستردة', 'Refunds', 'expense'],
            [LedgerAccount::ADJUSTMENTS, 'تسويات', 'Adjustments', 'equity'],
            [LedgerAccount::DISPUTE_HOLD, 'مبالغ محتجزة للنزاعات', 'Dispute hold', 'liability'],
        ];

        foreach ($accounts as [$code, $ar, $en, $type]) {
            LedgerAccount::updateOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'type' => $type, 'currency' => 'EGP',
            ]);
        }
    }

    private function normalise(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}