<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Auth\JwtTokenService;
use App\Support\Auth\RefreshTokenService;
use App\Support\Auth\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, JwtTokenService $jwt, RefreshTokenService $refreshTokens, UserProfile $profiles): JsonResponse
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

            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng hoặc tài khoản đang bị khóa.',
            ], 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Auth::setUser($user);

        Auditor::record('auth.login', $user, null, ['login' => $data['login']], $request, ['surface' => 'api']);

        [$refreshToken] = $refreshTokens->create($user, $request);
        $accessToken = $jwt->issue($user);

        return response()->json([
            ...$accessToken,
            'refresh_token' => $refreshToken,
            'user' => $profiles->forUser($user),
        ]);
    }

    public function refresh(Request $request, JwtTokenService $jwt, RefreshTokenService $refreshTokens, UserProfile $profiles): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $rotated = $refreshTokens->rotate($data['refresh_token'], $request);

        if (! $rotated) {
            return response()->json(['message' => 'Refresh token không hợp lệ hoặc đã hết hạn.'], 401);
        }

        [$refreshToken, , $user] = $rotated;
        $accessToken = $jwt->issue($user);

        return response()->json([
            ...$accessToken,
            'refresh_token' => $refreshToken,
            'user' => $profiles->forUser($user),
        ]);
    }

    public function logout(Request $request, RefreshTokenService $refreshTokens): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $refreshTokens->revokeForUser($user, $data['refresh_token'] ?? null);
        Auditor::record('auth.logout', $user, ['email' => $user->email], null, $request, ['surface' => 'api']);

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function profile(Request $request, UserProfile $profiles): JsonResponse
    {
        return response()->json([
            'user' => $profiles->forUser($request->user()),
        ]);
    }
}
