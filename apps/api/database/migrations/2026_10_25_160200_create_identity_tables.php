<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity: OTP (#13,#16), social identities (#13), risk-based verification (#8,#26,#28),
 * KYC documents (#38), purpose-specific consents (#235), tamper-evident audit (#215).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('purpose', 32)->default('login'); // login|verify_phone|recover|step_up
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('auth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32); // phone|password|google|apple
            $table->string('provider_user_id')->nullable();
            $table->string('secret_hash')->nullable(); // password hash when provider=password
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending'); // pending|verified|rejected|expired
            $table->string('method', 32)->default('document'); // document|eKYC|manual
            $table->string('risk_level', 16)->default('standard'); // low|standard|high
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('identity_verification_id')->nullable()->constrained('identity_verifications')->nullOnDelete();
            $table->string('doc_type', 40); // national_id|passport|licence|qualification|employer_noc
            $table->string('disk', 32)->default('local');
            $table->string('path'); // private storage path; never public
            $table->string('mime', 96)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('scan_status', 24)->default('pending'); // pending|clean|infected|rejected
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['user_id', 'doc_type']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 64)->index(); // tracking|clinical_data|media|marketing|data_processing
            $table->string('version', 16);
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('evidence_ref')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'granted']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 96)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 48)->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('purpose', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('identity_verifications');
        Schema::dropIfExists('auth_identities');
        Schema::dropIfExists('phone_otps');
    }
};