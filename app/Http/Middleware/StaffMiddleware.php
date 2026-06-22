<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('admin_logged_in')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Oturum gerekli.'], 401);
            }

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
