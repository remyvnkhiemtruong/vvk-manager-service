<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResourcePermission
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        $resource = (string) $request->route('resource');
        $definition = config('school.resources.'.$resource);
        $permission = $definition['permission'] ?? null;
        $user = $request->user();

        abort_unless($permission && $user && $user->hasPermission($permission.'.'.$action), 403);

        return $next($request);
    }
}
