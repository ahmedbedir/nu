<?php

namespace App\Http\Controllers\Api;

use App\Domain\Financials\Services\PaymentWebhookService;
use App\Domain\Financials\Services\RefundService;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly PaymentWebhookService $payments,
    ) {}

    /** POST /api/v1/invoices/{invoice}/cash-attestation */
    public function cashAttestation(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'party' => ['required', Rule::in(['provider', 'customer'])],
            'claim' => ['required', Rule::in(['paid', 'not_paid'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // A customer may only attest as the customer of that invoice.
        if ($data['party'] === 'customer' && (int) $invoice->customer_user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $result = $this->payments->confirmCash(
            $invoice,
            $data['party'],
            $data['claim'],
            $request->user()->id,
            isset($data['amount']) ? number_format((float) $data['amount'], 2, '.', '') : null,
        );

        return response()->json(['invoice' => [
            'public_id' => $invoice->public_id,
            'state' => $result['state'],
        ]]);
    }

    /** POST /api/v1/refund-requests */
    public function requestRefund(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_public_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['original', 'credit'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $invoice = Invoice::query()->where('public_id', $data['invoice_public_id'])->firstOrFail();

        if ((int) $invoice->customer_user_id !== (int) $request->user()->id
            && ! $request->user()->hasPermission('finance.view')) {
            abort(403);
        }

        $refund = $this->refunds->request(
            $invoice,
            number_format((float) $data['amount'], 2, '.', ''),
            $data['method'],
            $data['reason'],
            $request->user(),
        );

        return response()->json(['refund' => [
            'public_id' => $refund->public_id,
            'amount' => (string) $refund->amount,
            'method' => $refund->method,
            'state' => $refund->state,
        ]], 201);
    }

    /** POST /api/v1/finance/refunds/{refund}/approve — staff only, manager permission. */
    public function approveRefund(Request $request, Refund $refund): JsonResponse
    {
        $data = $request->validate([
            'second_approver_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $second = null;

        if (! empty($data['second_approver_phone'])) {
            $second = User::query()->where('phone', $data['second_approver_phone'])->first();

            if (! $second) {
                return response()->json(['message' => 'الموافق الثاني غير موجود', 'code' => 'second_approver_not_found'], 422);
            }
        }

        $approved = $this->refunds->approve($refund, $request->user(), $second);

        return response()->json(['refund' => [
            'public_id' => $approved->public_id,
            'state' => $approved->state,
            'amount' => (string) $approved->amount,
        ]]);
    }

    /** POST /api/v1/invoices/{invoice}/disputes */
    public function openDispute(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(['cash_mismatch', 'overcharge', 'no_show', 'refund', 'other'])],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $dispute = $this->refunds->openDispute($invoice, $data['category'], $data['description'], $request->user());

        return response()->json(['dispute' => [
            'public_id' => $dispute->public_id,
            'state' => $dispute->state,
            'category' => $dispute->category,
        ]], 201);
    }
}