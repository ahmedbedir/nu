<?php

namespace App\Support\Console;

use Illuminate\Console\Events\CommandStarting;

/**
 * Safety guard — hard-blocks destructive Artisan commands against a protected database.
 *
 * The user's local XAMPP database `nu` must never be wiped, reinitialised or reset.
 * Tests and destructive operations belong on `nu_test`. This guard is a fail-closed
 * backstop so that neither a person nor a script can accidentally destroy `nu`.
 */
class DestructiveCommandGuard
{
    /** Commands that may drop/truncate/reset data. */
    private const BLOCKED = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:refresh',
        'migrate:rollback',
        'db:wipe',
        'db:seed',
    ];

    /** Databases that must never be touched by a blocked command. */
    private const PROTECTED = ['nu'];

    public function handle(CommandStarting $event): void
    {
        $name = $event->command;

        if (! in_array($name, self::BLOCKED, true)) {
            return;
        }

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (in_array($database, self::PROTECTED, true)) {
            $this->abort($name, $database);
        }
    }

    private function abort(string $command, string $database): void
    {
        $message = PHP_EOL
            .'  ⛔ Safety guard: `'.$command.'` is forbidden against the protected database `'.$database.'`.'.PHP_EOL
            .'  The user\'s existing database must be preserved. Use isolated test databases (e.g. `nu_test`).'.PHP_EOL
            .'  Allowed here: additive `php artisan migrate`, `migrate:status`.'.PHP_EOL.PHP_EOL;

        if (defined('STDERR')) {
            fwrite(STDERR, $message);
        }

        exit(1);
    }
}