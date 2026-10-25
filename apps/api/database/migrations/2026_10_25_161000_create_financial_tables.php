<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing, invoices, double-entry ledger, payments, refunds and disputes
 * (PRD-09, #186–#195). Money is DECIMAL(18,2) EGP — never float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('kind', 32); // base|callout|per_minute|travel_per_km|night_uplift|holiday_uplift|commission
            $table->decimal('value', 18, 4);
            $table->string('unit', 16)->default('EGP'); // EGP|percent
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['service_id', 'region_id', 'kind', 'is_active'], 'hc_price_rule_lookup');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 24)->default('draft_invoice'); // draft_invoice|finalized|due|capture_pending|paid_verified|cash_claimed|cash_confirmed|disputed|refunded|partially_refunded|settled
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('commission_total', 18, 2)->default(0);
            $table->string('currency', 8)->default('EGP');
            $table->json('pricing_snapshot')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'state']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('kind', 32); // callout|procedure|time|travel|uplift|consumable|discount|refund
            $table->string('description_ar');
            $table->string('description_en');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2);
            $table->decimal('commission_amount', 18, 2)->default(0);
            $table->boolean('commissionable')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('gateway', 32)->default('mock'); // mock|paymob|stripe|...
            $table->string('method', 24)->default('card'); // card|wallet|instapay|cash
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('state', 24)->default('created'); // created|authorized|capture_pending|paid_verified|failed|cancelled
            $table->string('gateway_reference')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_event_id', 96)->unique(); // idempotency (T-045)
            $table->string('event_type', 48);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('type', 16); // asset|liability|revenue|expense|equity
            $table->string('currency', 8)->default('EGP');
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('journal_id')->index(); // one balanced journal per business event
            $table->foreignId('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('side', 8); // debit|credit
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('memo')->nullable();
            $table->timestamp('posted_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['journal_id', 'side']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('cash_collection_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('party', 16); // provider|customer
            $table->string('claim', 16); // paid|not_paid
            $table->decimal('amount', 18, 2)->nullable();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->useCurrent();
            $table->timestamps();
            $table->unique(['invoice_id', 'party']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('method', 24)->default('original'); // original|credit
            $table->string('state', 24)->default('requested'); // requested|approved|rejected|processed
            $table->string('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('state', 24)->default('open'); // open|investigating|resolved|escalated
            $table->string('category', 32); // cash_mismatch|overcharge|no_show|refund|other
            $table->text('description');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->decimal('amount', 18, 2)->default(0); // positive = provider owes platform
            $table->string('currency', 8)->default('EGP');
            $table->string('kind', 32)->default('cash_commission'); // cash_commission|offset|adjustment
            $table->string('status', 16)->default('open'); // open|settled|written_off
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->timestamps();

            $table->index(['professional_profile_id', 'status']);
        });

        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('period_start', 32);
            $table->string('period_end', 32);
            $table->string('status', 16)->default('draft'); // draft|approved|paid|cancelled
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
        Schema::dropIfExists('provider_receivables');
        Schema::dropIfExists('financial_disputes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('cash_collection_confirmations');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('price_rules');
    }
};