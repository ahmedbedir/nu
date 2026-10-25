<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds the non-enumerable public identifier to users.
 *
 * The account principal is referenced by `public_id` everywhere; the internal
 * bigint id never leaves the database. Additive and backfill-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'public_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
        });

        // Backfill any pre-existing rows deterministically.
        foreach (DB::table('users')->whereNull('public_id')->orderBy('id')->cursor() as $row) {
            DB::table('users')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};