<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional onboarding (PRD-02, decisions #33–#62, #38–#41).
 *
 * One user account may hold several independent professional profiles; each has
 * its own credentials; service-level eligibility is separate from account status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('category', 32); // nursing|personal_care|lab|physio|other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profession_id')->constrained('professions')->restrictOnDelete();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('years_experience_self_declared')->default(0);
            $table->boolean('declares_external_employment')->default(false);
            $table->string('noc_document_ref')->nullable();
            $table->string('status', 24)->default('pending'); // pending|active|suspended|rejected|retired
            $table->string('gender', 12)->nullable();
            $table->decimal('home_lat', 10, 7)->nullable();
            $table->decimal('home_lng', 10, 7)->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'profession_id']);
            $table->index(['status', 'profession_id']);
        });

        Schema::create('professional_credentials', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->string('credential_type', 32); // license|qualification|union_registration|noc|speciality_cert
            $table->string('title');
            $table->string('issuing_body')->nullable();
            $table->string('number_ciphertext')->nullable(); // app-layer encrypted, never logged
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('document_ref')->nullable();
            $table->string('status', 24)->default('pending'); // pending|verified|rejected|expired|suspended
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('credential_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('professional_credentials')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verdict', 16); // approved|rejected|flagged
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['credential_id', 'verdict']);
        });

        Schema::create('provider_service_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->string('service_code', 64); // resolved against catalog at runtime
            $table->string('status', 24)->default('pending'); // pending|approved|denied|suspended
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['professional_profile_id', 'service_code'], 'hc_prof_service_unique');
        });

        Schema::create('provider_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->unsignedSmallInteger('radius_km')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['professional_profile_id', 'region_id']);
        });

        Schema::create('provider_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_profile_id')->constrained('professional_profiles')->cascadeOnDelete();
            $table->string('status', 16)->default('offline'); // offline|online|busy|trip|visit|rest
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_presence');
        Schema::dropIfExists('provider_areas');
        Schema::dropIfExists('provider_service_approvals');
        Schema::dropIfExists('credential_reviews');
        Schema::dropIfExists('professional_credentials');
        Schema::dropIfExists('professional_profiles');
        Schema::dropIfExists('professions');
    }
};