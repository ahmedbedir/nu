<?php

namespace Database\Seeders;

use App\Domain\Identity\Support\RbacCatalog;
use Illuminate\Database\Seeder;

/**
 * Test/database seeding entry point. Runs against `nu_test` only — the safety guard
 * in DestructiveCommandGuard blocks `db:seed` against `nu`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        RbacCatalog::sync();

        $this->command?->info('RBAC catalogue synced.');
    }
}