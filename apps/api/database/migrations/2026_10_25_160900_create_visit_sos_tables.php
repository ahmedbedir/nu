<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visits, clinical notes and SOS (PRD-07, PRD-08, #168–#185).
 *
 * CRITICAL: visit time starts only after a VERIFIED TWO-SIDED check-in; a GPS
 * geofence observation is recorded separately and never starts billing (T-030).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->restrictOnDelete();
            $table->string('state', 32)->default('scheduled'); // scheduled|departing|en_route|arrival_claimed|arrival_confirmed|in_session|provider_completed|customer_review|invoiced|settled
            $table->timestamp('departure_eta')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrival_claimed_at')->nullable();
            $table->timestamp('arrival_confirmed_at')->nullable();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ended_at')->nullable();
            $table->unsignedInteger('billed_minutes')->nullable();
            $table->json('checkin_evidence')->nullable();
            $table->json('geofence_observations')->nullable();
            $table->timestamps();
        });

        Schema::create('visit_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('party', 16); // provider|customer
            $table->string('kind', 24); // claimed|confirmed|geofence_observed
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['visit_id', 'kind']);
        });

        Schema::create('visit_time_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('kind', 24); // started|stopped|corrected
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('visit_procedures', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('code', 64);
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('price', 18, 2)->default(0);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 24)->default('booked'); // booked|added|corrected
            $table->timestamps();
        });

        Schema::create('visit_procedure_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('visit_procedures')->cascadeOnDelete();
            $table->text('original_snapshot');
            $table->text('corrected_notes');
            $table->string('reason');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('visit_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('kind', 24); // clinical_photo|report|consent|evidence
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 96)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('requires_consent')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sos_incidents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('professional_profile_id')->nullable()->constrained('professional_profiles')->nullOnDelete();
            $table->string('reporter_role', 16); // customer|professional
            $table->string('state', 24)->default('initiated_local'); // initiated_local|accepted_server|notified_ops|acknowledged|coordinating|resolved|closed
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('contact_phone_ciphertext')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->foreignId('acked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closure_reason', 24)->nullable(); // reporter_acknowledged|senior_review
            $table->text('closure_notes')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
        });

        Schema::create('sos_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_incident_id')->constrained('sos_incidents')->cascadeOnDelete();
            $table->string('channel', 24); // push|sms|phone|slack|internal
            $table->string('status', 24)->default('pending'); // pending|sent|delivered|failed|retry_pending
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->index(['sos_incident_id', 'status']);
        });

        Schema::create('sos_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_incident_id')->constrained('sos_incidents')->cascadeOnDelete();
            $table->foreignId('acknowledged_by')->constrained('users')->restrictOnDelete();
            $table->string('channel', 24);
            $table->timestamp('acknowledged_at')->useCurrent();
            $table->text('notes')->nullable();
        });

        Schema::create('sos_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_incident_id')->constrained('sos_incidents')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24)->default('push');
            $table->timestamp('escalated_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('status', 16)->default('pending'); // pending|acknowledged|resolved
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalations');
        Schema::dropIfExists('sos_acknowledgements');
        Schema::dropIfExists('sos_delivery_attempts');
        Schema::dropIfExists('sos_incidents');
        Schema::dropIfExists('visit_documents');
        Schema::dropIfExists('visit_procedure_corrections');
        Schema::dropIfExists('visit_procedures');
        Schema::dropIfExists('visit_time_events');
        Schema::dropIfExists('visit_checkins');
        Schema::dropIfExists('visits');
    }
};