<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service catalog with clinical review (PRD-03, #63–#97).
 * Admin defines; medical reviewer approves; snapshots are preserved after booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('category_id')->constrained('service_categories')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->foreignId('profession_id')->constrained('professions')->restrictOnDelete();
            $table->string('billing_mode', 24)->default('fixed'); // fixed|time
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->string('status', 24)->default('draft'); // draft|published|archived|suspended
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('requires_medical_order')->default(false);
            $table->string('order_validation_method', 32)->nullable(); // document|listed_source|review
            $table->json('required_fields')->nullable();
            $table->json('red_flag_screen')->nullable();
            $table->json('allowed_age_range')->nullable();
            $table->json('allowed_genders')->nullable();
            $table->decimal('base_price', 18, 2)->default(0);
            $table->string('currency', 8)->default('EGP');
            $table->timestamps();

            $table->index(['status', 'profession_id']);
        });

        Schema::create('service_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot');
            $table->string('change_note')->nullable();
            $table->foreignId('editor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'version']);
        });

        Schema::create('service_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('price', 18, 2)->default(0);
            $table->string('billing_mode', 24)->default('fixed'); // fixed|time
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'code']);
        });

        Schema::create('clinical_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('version', 16);
            $table->json('schema'); // ordered fields with types, required flags, safety flags
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['service_id', 'version']);
        });

        Schema::create('medical_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('prescriber_name')->nullable();
            $table->string('order_number')->nullable();
            $table->date('order_date')->nullable();
            $table->string('status', 24)->default('pending'); // pending|valid|unclear|review|blocked|void
            $table->string('document_ref')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_orders');
        Schema::dropIfExists('clinical_form_templates');
        Schema::dropIfExists('service_procedures');
        Schema::dropIfExists('service_versions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};