<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Yalnızca tam yetkili admin (kasa ve garson erişemez). */
class AdminOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('admin_role') !== User::ROLE_ADMIN) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Bu alan yalnızca yönetici içindir.'], 403);
            }

            $redirect = match (session('admin_role')) {
                User::ROLE_WAITER => route('waiter.dashboard'),
                User::ROLE_CASHIER => route('admin.live-orders.index'),
                default => route('admin.login'),
            };

            return redirect()
                ->to($redirect)
                ->with('error', 'Bu alan yalnızca yönetici içindir.');
        }

        return $next($request);
    }
}
