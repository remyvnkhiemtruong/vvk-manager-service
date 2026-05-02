<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit\Auditor;
use App\Support\Auth\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, UserProfile $profiles): Response
    {
        return Inertia::render('Auth/Profile', [
            'profile' => $profiles->forUser($request->user()),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Mật khẩu hiện tại không đúng.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        Auditor::record(
            'auth.password_changed',
            $user,
            ['password' => '[redacted]'],
            ['password' => '[redacted]'],
            $request
        );

        return back()->with('success', 'Mật khẩu đã được cập nhật.');
    }
}
