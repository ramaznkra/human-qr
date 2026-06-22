<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oturum kontrolü (admin + garson). Garsonların /admin yönetim sayfalarına girmesi engellenir.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('admin_logged_in')) {
            if ($request->expectsJson() || $request->is('admin/api/*', 'api/waiter/*', 'api/admin/*')) {
                return response()->json(['message' => 'Oturum gerekli.'], 401);
            }

            return redirect()->route('admin.login');
        }

        $isAdminPanel = $request->is('admin/*')
            && ! $request->is('admin/cikis', 'admin/giris', 'admin/api/*');

        if (session('admin_role') === User::ROLE_WAITER && $isAdminPanel) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Garson yönetim paneline erişemez.'], 403);
            }

            return redirect()
                ->route('waiter.dashboard')
                ->with('error', 'Bu alan yalnızca yönetici içindir.');
        }

        $response = $next($request);

        if ($isAdminPanel && ! $request->expectsJson()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
