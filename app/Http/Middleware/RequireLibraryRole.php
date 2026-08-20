<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RequireLibraryRole
{
    /**
     * Roles that may access library API endpoints.
     */
    private const ALLOWED_ROLES = ['trial-user', 'full-user', 'admin', 'super-admin'];

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !isset($user->role)) {
            Log::warning('Library role access denied: not authenticated', [
                'uri' => $request->getRequestUri(),
                'reason' => !$user ? 'not_authenticated' : 'role_not_set',
            ]);
            if (!$request->expectsJson()) {
                return $next($request);
            }
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $role = $user->role;

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            Log::warning('Library role access denied: insufficient role', [
                'uri' => $request->getRequestUri(),
                'user_id' => $user->id,
                'user_role' => $role,
            ]);
            if (!$request->expectsJson()) {
                return $next($request);
            }
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
