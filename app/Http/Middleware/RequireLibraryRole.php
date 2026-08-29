<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\UserRoles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Gates the authenticated API surface on the account having been verified.
 *
 * On the full server this also picked a book source (local vs. LibriVox) from
 * the caller's role. Lite has no book catalog and no source modes, so the only
 * check left is verified vs. unverified — see {@see UserRoles}. Enumerating
 * allowed roles here is what previously locked `user` (the plain player role)
 * out of every sync endpoint with a 403.
 */
class RequireLibraryRole
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !isset($user->role)) {
            Log::warning('Sync access denied: not authenticated', [
                'uri' => $request->getRequestUri(),
                'reason' => !$user ? 'not_authenticated' : 'role_not_set',
            ]);
            if (!$request->expectsJson()) {
                return $next($request);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!UserRoles::isVerified($user->role)) {
            Log::warning('Sync access denied: account not verified', [
                'uri' => $request->getRequestUri(),
                'user_id' => $user->id,
                'user_role' => $user->role,
            ]);
            if (!$request->expectsJson()) {
                return $next($request);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
