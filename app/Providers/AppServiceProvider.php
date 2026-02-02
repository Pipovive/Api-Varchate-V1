<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return URL::to('/api/password/reset')
                . '?token=' . $token
                . '&email=' . urlencode($user->email);
        });

        RateLimiter::for('email-resend', function (Request $request) {
            return Limit::perMinutes(5, 3)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });
    }
}
