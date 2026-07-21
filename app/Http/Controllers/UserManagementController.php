<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(TenantService $tenant): View
    {
        $user = Auth::user();
        return view('users.index', [
            'users' => $tenant->scope(User::with('role'), $user)->orderBy('name')->paginate(30),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit, TenantService $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:10', 'max:120', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/'],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $role = Role::findOrFail($data['role_id']);
        if (! Auth::user()?->isSuperAdmin() && $role->name === 'SUPER_ADMIN') {
            return back()->withErrors(['role_id' => 'Only SUPER_ADMIN can create another SUPER_ADMIN.']);
        }
        $user = User::create([
            'company_id' => $tenant->defaultCompanyId(Auth::user()),
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'must_change_password' => true,
        ]);
        $audit->log(Auth::id(), 'USER_CREATE', 'users', (string) $user->id, ['role_id' => $user->role_id], $request);

        return back()->with('status', 'User account created');
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        if (! Auth::user()?->isSuperAdmin() && $user->company_id !== Auth::user()?->company_id) {
            abort(404);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:10', 'max:120', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        if ($user->id === Auth::id() && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }
        $targetRole = Role::findOrFail($data['role_id']);
        if (! Auth::user()?->isSuperAdmin() && $targetRole->name === 'SUPER_ADMIN') {
            return back()->withErrors(['role_id' => 'Only SUPER_ADMIN can assign SUPER_ADMIN role.']);
        }

        $superAdminRoleId = Role::query()->where('name', 'SUPER_ADMIN')->value('id');
        $activeSuperAdmins = User::query()->where('role_id', $superAdminRoleId)->where('is_active', true)->count();
        if ($superAdminRoleId
            && $user->role_id === (int) $superAdminRoleId
            && $activeSuperAdmins <= 1
            && ((int) $data['role_id'] !== (int) $superAdminRoleId || ! $request->boolean('is_active'))
        ) {
            return back()->withErrors(['role_id' => 'At least one active SUPER_ADMIN is required.']);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'is_active' => $request->boolean('is_active'),
            'must_change_password' => $request->boolean('must_change_password'),
        ];
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $payload['must_change_password'] = true;
        }

        $user->update($payload);
        $audit->log(Auth::id(), 'USER_UPDATE', 'users', (string) $user->id, ['role_id' => $user->role_id], $request);

        return back()->with('status', 'User account updated');
    }
}
