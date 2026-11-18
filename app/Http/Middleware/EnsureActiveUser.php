<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            // A deactivated account must not remain usable through an existing
            // session or a previously issued "remember me" cookie.
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'این حساب کاربری غیرفعال است.',
            ]);
        }

        return $next($request);
    }
}
