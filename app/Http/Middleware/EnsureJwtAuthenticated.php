<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Auth\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureJwtAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Thiếu access token.'], 401);
        }

        try {
            $claims = app(JwtTokenService::class)->decode($token);
        } catch (Throwable) {
            return response()->json(['message' => 'Access token không hợp lệ hoặc đã hết hạn.'], 401);
        }

        $user = User::query()
            ->whereKey($claims['sub'])
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Tài khoản không hợp lệ hoặc đã bị khóa.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}
