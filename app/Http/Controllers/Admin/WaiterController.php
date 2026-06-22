<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WaiterController extends Controller
{
    public function index(): View
    {
        $waiters = User::query()
            ->whereIn('role', User::MANAGEABLE_ROLES)
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'cashier' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return view('admin.waiters.index', compact('waiters'));
    }

    public function create(): View
    {
        $defaultRole = request()->query('role', User::ROLE_WAITER);
        if (! in_array($defaultRole, User::MANAGEABLE_ROLES, true)) {
            $defaultRole = User::ROLE_WAITER;
        }

        return view('admin.waiters.form', [
            'waiter' => new User(['role' => $defaultRole, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|max:72',
            'role' => ['required', Rule::in(User::MANAGEABLE_ROLES)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.waiters.index')
            ->with('success', $this->createdMessage($data['role']));
    }

    public function editPanel(User $waiter): View
    {
        return view('admin.waiters.panel', compact('waiter'));
    }

    public function edit(User $waiter): View
    {
        return view('admin.waiters.form', compact('waiter'));
    }

    public function update(Request $request, User $waiter): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($waiter->id),
            ],
            'password' => 'nullable|string|min:8|max:72',
            'role' => ['required', Rule::in(User::MANAGEABLE_ROLES)],
        ]);

        if ($waiter->isAdmin()
            && $data['role'] !== User::ROLE_ADMIN
            && $this->activeAdminCount() <= 1) {
            if ($request->header('X-Admin-Drawer')) {
                return response()->json(['success' => false, 'message' => 'Son aktif admin hesabının rolü değiştirilemez.'], 422);
            }

            return back()->with('error', 'Son aktif admin hesabının rolü değiştirilemez.');
        }

        $waiter->name = $data['name'];
        $waiter->email = $data['email'];
        $waiter->role = $data['role'];

        if (! empty($data['password'])) {
            $waiter->password = $data['password'];
        }

        $waiter->save();

        $message = $waiter->staffRoleLabel().' bilgileri güncellendi.';

        if ($request->header('X-Admin-Drawer')) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()
            ->route('admin.waiters.index')
            ->with('success', $message);
    }

    public function destroy(User $waiter): RedirectResponse
    {
        if ((int) session('admin_user_id') === (int) $waiter->id) {
            return back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }

        if ($waiter->isAdmin() && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'Son aktif admin hesabı silinemez.');
        }

        $label = $waiter->staffRoleLabel();
        $waiter->delete();

        return redirect()
            ->route('admin.waiters.index')
            ->with('success', $label.' hesabı silindi.');
    }

    public function toggleActive(User $waiter): JsonResponse
    {
        if ((int) session('admin_user_id') === (int) $waiter->id) {
            return response()->json([
                'success' => false,
                'message' => 'Kendi hesabınızı pasife alamazsınız.',
            ], 422);
        }

        if ($waiter->isAdmin() && $waiter->is_active && $this->activeAdminCount() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Son aktif admin hesabı pasife alınamaz.',
            ], 422);
        }

        $waiter->update(['is_active' => ! $waiter->is_active]);

        return response()->json([
            'success' => true,
            'waiter_id' => $waiter->id,
            'is_active' => $waiter->is_active,
            'label' => $waiter->is_active ? 'Aktif' : 'Pasif',
        ]);
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->count();
    }

    private function createdMessage(string $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'Admin hesabı oluşturuldu.',
            User::ROLE_CASHIER => 'Kasa hesabı oluşturuldu.',
            default => 'Garson hesabı oluşturuldu.',
        };
    }
}
