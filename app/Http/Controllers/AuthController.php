<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditService $audit)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'اطلاعات ورود صحیح نیست.']);
        }if (! $request->user()->active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'این حساب غیرفعال است.']);
        }$request->session()->regenerate();
        $audit->record('login', 'USER', $request->user()->id, [], [], 'ورود به سیستم');

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
