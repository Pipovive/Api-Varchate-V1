<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return env('FRONTEND_URL', 'http://127.0.0.1:8000')
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($user->email);
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });

        $this->app->resolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('brevo', function () {
                $factory = new BrevoTransportFactory();
                $dsn = Dsn::fromString(
                    'brevo+api://' . config('services.brevo.key') . '@default'
                );
                return $factory->create($dsn);
            });
        });
    }
}
