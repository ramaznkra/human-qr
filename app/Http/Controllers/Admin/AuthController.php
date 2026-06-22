<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scopes\RestaurantScope;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('admin_logged_in')) {
            return redirect()->to($this->homeForRole(session('admin_role', User::ROLE_ADMIN)));
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::withoutGlobalScope(RestaurantScope::NAME)
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->with('error', 'E-posta veya şifre hatalı.');
        }

        if ($user->is_active === false) {
            return back()->with('error', 'Hesabınız pasif. Yöneticinizle iletişime geçin.');
        }

        if (! $user->restaurant_id) {
            return back()->with('error', 'Kullanıcıya restoran atanmamış.');
        }

        session([
            'admin_logged_in' => true,
            'admin_user_id' => $user->id,
            'admin_name' => $user->name,
            'admin_role' => $user->role ?? User::ROLE_ADMIN,
            'admin_restaurant_id' => $user->restaurant_id,
        ]);

        return redirect()->to($this->homeForRole($user->role ?? User::ROLE_ADMIN));
    }

    public function logout(): RedirectResponse
    {
        session()->forget(['admin_logged_in', 'admin_user_id', 'admin_name', 'admin_role', 'admin_restaurant_id']);

        return redirect()->route('admin.login');
    }

    private function homeForRole(string $role): string
    {
        return match ($role) {
            User::ROLE_WAITER => route('waiter.dashboard'),
            User::ROLE_CASHIER => route('admin.live-orders.index'),
            default => route('admin.dashboard'),
        };
    }
}
