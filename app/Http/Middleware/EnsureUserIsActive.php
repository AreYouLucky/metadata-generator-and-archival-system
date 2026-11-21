<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a deactivated account on its next request, so deactivation takes
 * effect immediately and not only at the next login.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort_if($request->expectsJson() && ! $request->header('X-Inertia'), Response::HTTP_FORBIDDEN, 'This account has been deactivated.');

            return to_route('login')->with('status', 'This account has been deactivated. Contact an administrator.');
        }

        return $next($request);
    }
}
