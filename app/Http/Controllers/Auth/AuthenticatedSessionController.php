<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::findForLogin($data['login']);

        if (! $user || $user->status !== 'active' || ! Hash::check($data['password'], $user->password)) {
            Auditor::record(
                'auth.login_failed',
                $user,
                null,
                ['login' => $data['login'], 'reason' => $user?->status === 'active' ? 'invalid_credentials' : 'inactive_or_missing'],
                $request
            );

            return back()->withErrors([
                'login' => 'Thông tin đăng nhập không đúng hoặc tài khoản đang bị khóa.',
            ])->onlyInput('login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        Auditor::record('auth.login', $user, null, ['login' => $data['login']], $request, ['surface' => 'web']);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Auditor::record('auth.logout', $user, ['email' => $user->email], null, $request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
