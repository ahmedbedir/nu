<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurrence, shifts, subscriptions and ongoing support (PRD-06, #133–#167).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->date('exception_date')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('kind', 16)->default('unavailable'); // unavailable|leave|holiday|break
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['professional_profile_id', 'exception_date', 'starts_at', 'ends_at'], 'hc_exception_unique');
        });

        Schema::create('recurring_series', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->cascadeOnDelete();
            $table->string('rrule'); // RFC-5545-like
            $table->timestamp('starts_on')->nullable();
            $table->timestamp('ends_on')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('series_id')->constrained('recurring_series')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('state', 24)->default('planned_unconfirmed'); // planned_unconfirmed|reservation_held|booked|completed|skipped|rescheduled|cancelled
            $table->timestamps();

            $table->index(['series_id', 'state']);
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('length_hours'); // 6|8|12
            $table->string('status', 16)->default('scheduled'); // scheduled|active|completed|cancelled
            $table->timestamps();

            $table->index(['professional_profile_id', 'starts_at']);
        });

        Schema::create('clinical_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->text('summary');
            $table->json('procedures_done')->nullable();
            $table->string('to_professional_name')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 48)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('kind', 24); // visit_units|hour_units|membership_discount
            $table->unsignedInteger('units')->default(0);
            $table->decimal('price', 18, 2)->default(0);
            $table->string('currency', 8)->default('EGP');
            $table->json('service_codes')->nullable();
            $table->json('region_codes')->nullable();
            $table->boolean('family_shared')->default(false);
            $table->unsignedTinyInteger('carry_forward_months')->default(0);
            $table->unsignedTinyInteger('max_pauses')->default(1);
            $table->unsignedSmallInteger('pause_max_days')->default(30);
            $table->string('renewal_mode', 24)->default('manual_consent'); // manual_consent|auto_consent
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->unsignedInteger('units_remaining')->default(0);
            $table->unsignedInteger('used_units')->default(0);
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('status', 16)->default('active'); // active|paused|expired|cancelled
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->unsignedTinyInteger('pauses_used')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained('customer_subscriptions')->cascadeOnDelete();
            $table->string('reference_type', 32);
            $table->string('reference_id', 64);
            $table->unsignedInteger('units')->default(1);
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();

            $table->unique(['customer_subscription_id', 'reference_type', 'reference_id'], 'hc_sub_usage_unique');
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('category', 32)->default('general');
            $table->string('state', 24)->default('open'); // open|in_progress|resolved|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_approvals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('kind', 48); // refund_large|provider_ban|price_change|break_glass|payout
            $table->string('action');
            $table->json('payload');
            $table->string('status', 16)->default('pending'); // pending|approved|rejected|escalated
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_approvals');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('subscription_usage');
        Schema::dropIfExists('customer_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('clinical_handovers');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_series');
        Schema::dropIfExists('provider_exceptions');
        Schema::dropIfExists('provider_availability');
    }
};