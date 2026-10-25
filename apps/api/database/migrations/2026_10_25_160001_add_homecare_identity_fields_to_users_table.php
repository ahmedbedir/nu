<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('phone_country', 5)->default('+20')->after('phone');
            $table->string('locale', 8)->default('ar')->after('phone_country');
            $table->string('status', 24)->default('active')->index()->after('locale'); // active|pending|suspended|disabled
            $table->string('identity_status', 24)->default('unverified')->index()->after('status'); // unverified|pending|verified|rejected|expired
            $table->timestamp('identity_verified_at')->nullable()->after('identity_status');
            $table->string('gender', 12)->nullable()->after('identity_verified_at'); // male|female|unspecified
            $table->date('birth_date')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'phone_country', 'locale', 'status',
                'identity_status', 'identity_verified_at', 'gender', 'birth_date',
            ]);
        });
    }
};