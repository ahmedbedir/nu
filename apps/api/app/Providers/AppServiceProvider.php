<?php

namespace App\Providers;

use App\Domain\Notifications\Channels\LogSmsSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Support\Console\DestructiveCommandGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SMS boundary: the log adapter is the only one that may be active locally.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    public function boot(): void
    {
        // Safety backstop: never let a destructive command touch the protected `nu` database.
        Event::listen(CommandStarting::class, DestructiveCommandGuard::class);

        // Super-admins bypass all gates (decision #206).
        Gate::before(function ($user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}