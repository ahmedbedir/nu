<?php

namespace App\Providers;

use App\Domain\Geo\Contracts\GeoDistance;
use App\Domain\Geo\Services\SqlGeoDistance;
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
        // SMS boundary: only the logging adapter may be active locally (#239 gate).
        $this->app->bind(SmsSender::class, LogSmsSender::class);

        // Engine-independent distance/ETA (ADR-002).
        $this->app->bind(GeoDistance::class, SqlGeoDistance::class);
    }

    public function boot(): void
    {
        // Safety backstop: never let a destructive command touch the protected `nu` database.
        Event::listen(CommandStarting::class, DestructiveCommandGuard::class);

        // Super-admins bypass authorisation gates (decision #206).
        Gate::before(function ($user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}