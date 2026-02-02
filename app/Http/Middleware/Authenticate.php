<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        // 🔥 CLAVE: si es API, NO redirigir
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}
