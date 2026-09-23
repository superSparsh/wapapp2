<?php

namespace App\Providers;

use App\Support\HorizonRole;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply role filter at runtime only. Never during config:cache / optimize,
        // or the filtered (web) map gets baked into bootstrap/cache/config.php
        // and oci-heavy starts with zero supervisors.
        if (! $this->isBuildingConfigCache()) {
            HorizonRole::applyToConfig();
        }

        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }

    private function isBuildingConfigCache(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';

        // `php artisan config:cache` sometimes places the command at index 1 or 2.
        foreach ($argv as $part) {
            if (in_array($part, ['config:cache', 'optimize'], true)) {
                return true;
            }
        }

        return in_array($command, ['config:cache', 'optimize'], true);
    }
}
