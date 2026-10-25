<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative geography (decision #227, #11) + patient records (#17, #18, #21, #22, #23, #24, #105, #80).
 *
 * CRITICAL: `patient_addresses` is the VISIT destination (#105). It is a separate object from
 * the booking device's GPS position and must never be substituted by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('level', 16)->index(); // governorate|city|district|village|sector
            $table->string('code', 24)->nullable()->index();
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->string('sex', 12)->default('unspecified'); // male|female|unspecified
            $table->string('national_id_ciphertext')->nullable(); // app-layer encrypted; never logged
            $table->string('blood_type', 8)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('conditions_state', 12)->default('unknown'); // known|unknown (#80)
            $table->string('medications_state', 12)->default('unknown');
            $table->string('allergies_state', 12)->default('unknown');
            $table->text('clinical_notes')->nullable();
            $table->string('status', 16)->default('active'); // active|archived|merged
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['full_name']);
        });

        Schema::create('patient_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 24); // self|parent|guardian|relative|caregiver
            $table->string('verification_status', 24)->default('pending'); // pending|verified|rejected
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['patient_id', 'user_id']);
            $table->index(['user_id', 'verification_status']);
        });

        Schema::create('patient_delegations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('grantee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes'); // ["book","pay","view_visit_status","view_medical"]
            $table->string('status', 16)->default('active')->index(); // active|revoked|expired
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('consent_evidence')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'grantee_user_id']);
        });

        Schema::create('patient_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->string('status', 16)->default('active'); // active|resolved
            $table->string('source', 24)->default('customer'); // customer|professional|reviewer
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('patient_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name');
            $table->string('dose')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route', 32)->nullable();
            $table->string('prescribed_by')->nullable();
            $table->string('status', 16)->default('active'); // active|stopped
            $table->string('source', 24)->default('customer');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('allergen');
            $table->string('reaction')->nullable();
            $table->string('severity', 16)->nullable(); // mild|moderate|severe
            $table->string('status', 16)->default('active');
            $table->string('source', 24)->default('customer');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('patient_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 32)->nullable();
            $table->string('phone', 20);
            $table->unsignedTinyInteger('priority'); // 1..3 (#21)
            $table->timestamps();
            $table->unique(['patient_id', 'priority']);
        });

        Schema::create('patient_addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('label', 48)->nullable();
            $table->string('address_line');
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->string('geocode_source', 32)->nullable();
            $table->string('verification_status', 24)->default('unverified'); // unverified|pending|verified
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['patient_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_addresses');
        Schema::dropIfExists('patient_emergency_contacts');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_medications');
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('patient_delegations');
        Schema::dropIfExists('patient_user_links');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('regions');
    }
};