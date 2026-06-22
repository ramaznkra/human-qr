<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WaiterOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('admin_role') !== User::ROLE_WAITER) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Garson oturumu gerekli.'], 403);
            }

            return redirect()
                ->route('admin.live-orders.index')
                ->with('error', 'Bu ekran yalnızca garson hesabı içindir.');
        }

        return $next($request);
    }
}
