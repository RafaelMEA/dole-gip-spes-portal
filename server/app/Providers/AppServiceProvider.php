<?php

namespace App\Providers;

use App\Listeners\WorkflowNotificationSubscriber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Workflow events -> in-app notifications. The subscriber is the one
        // authoritative place where domain events become notifications.
        Event::subscribe(WorkflowNotificationSubscriber::class);

        // Bind {notification} route parameters to Laravel's database
        // notification model; ownership is enforced in the controller.
        Route::model('notification', DatabaseNotification::class);
    }
}
