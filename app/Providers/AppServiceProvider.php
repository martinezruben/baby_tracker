<?php

namespace App\Providers;

use App\Services\Notifications\Push\MinishlinkPushClient;
use App\Services\Notifications\Push\PushClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushClient::class, MinishlinkPushClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
