<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: `role:encoder,admin`. The user must be active and hold one of the
 * listed roles. Admins pass every role check (see User::hasRole()).
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        abort_unless($user instanceof User && $user->is_active && $user->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
