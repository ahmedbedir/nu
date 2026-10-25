<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking, dispatch and reservations (PRD-05, #98–#132).
 *
 * The single-winner guarantee is enforced by a transactional `SELECT … FOR UPDATE`
 * plus a UNIQUE index on `booking_holds.active_key` — a generated column that is
 * non-null only for the one ACTIVE hold of a booking. MySQL/MariaDB have no
 * exclusion constraints, so this is the tested equivalent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('visit_address_id')->constrained('patient_addresses')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('required_gender', 12); // male|female
            $table->boolean('gender_consent_for_alternate')->default(false);
            $table->string('mode', 16)->default('instant'); // instant|advanced|choose
            $table->foreignId('chosen_professional_id')->nullable()->constrained('professional_profiles')->nullOnDelete();
            $table->string('state', 32)->default('draft')->index();
            $table->json('form_answers')->nullable();
            $table->decimal('booking_device_lat', 10, 7)->nullable(); // recorded, never used as destination
            $table->decimal('booking_device_lng', 10, 7)->nullable();
            $table->timestamp('requested_for')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'state']);
        });

        Schema::create('booking_idempotency', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('action', 48);
            $table->string('result_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_request_id')->constrained('booking_requests')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('visit_address_id')->constrained('patient_addresses')->restrictOnDelete();
            $table->foreignId('professional_profile_id')->nullable()->constrained('professional_profiles')->nullOnDelete();
            $table->string('state', 40)->default('search_pending')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamps();

            $table->index(['professional_profile_id', 'state']);
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'event']);
        });

        Schema::create('dispatch_waves', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('wave_number')->default(1);
            $table->unsignedSmallInteger('radius_km')->default(10);
            $table->unsignedSmallInteger('offer_ttl_seconds')->default(30);
            $table->string('status', 16)->default('open'); // open|closed|expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'wave_number']);
        });

        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('wave_id')->constrained('dispatch_waves')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->string('status', 16)->default('offered'); // offered|accepted|declined|expired|superseded
            $table->unsignedInteger('eta_seconds')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'professional_profile_id'], 'hc_offer_unique');
        });

        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->string('status', 16)->default('active'); // active|released|expired|consumed
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('release_reason_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();

            // Generated column: non-null ONLY for the single active hold of a booking.
            $table->string('active_key', 64)->nullable()->storedAs(
                "if(`status` = 'active', concat(`booking_id`), null)"
            );
            $table->unique('active_key', 'hc_booking_one_active_hold');
        });

        Schema::create('scheduling_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('blocked_until')->nullable(); // includes travel/rest buffer (decision #125)
            $table->string('status', 16)->default('held'); // held|confirmed|released|cancelled
            $table->timestamps();

            $table->index(['professional_profile_id', 'starts_at', 'status'], 'hc_res_slot_idx');
        });

        Schema::create('booking_quotes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('lines');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('total', 18, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('status', 16)->default('pending'); // pending|accepted|expired|superseded
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_quotes');
        Schema::dropIfExists('scheduling_reservations');
        Schema::dropIfExists('booking_holds');
        Schema::dropIfExists('dispatch_offers');
        Schema::dropIfExists('dispatch_waves');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_idempotency');
        Schema::dropIfExists('booking_requests');
    }
};