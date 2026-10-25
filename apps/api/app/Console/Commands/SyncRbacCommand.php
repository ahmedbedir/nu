<?php

namespace App\Console\Commands;

use App\Domain\Identity\Support\RbacCatalog;
use Illuminate\Console\Command;

/**
 * Idempotent RBAC reference-data sync.
 *
 * Deliberately a dedicated command rather than `db:seed`, so that reference data
 * can be updated on the protected `nu` database without enabling destructive seeding.
 */
class SyncRbacCommand extends Command
{
    protected $signature = 'homecare:sync-rbac';

    protected $description = 'Sync HomeCare roles & permissions (idempotent, non-destructive)';

    public function handle(): int
    {
        $result = RbacCatalog::sync();

        $this->info(sprintf(
            'RBAC synced: %d roles, %d permissions, %d grants.',
            $result['roles'],
            $result['permissions'],
            $result['grants'],
        ));

        return self::SUCCESS;
    }
}