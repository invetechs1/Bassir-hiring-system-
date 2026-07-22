@extends('layouts.app')
@section('title', 'User Management')
@section('content')
<div class="grid grid-2">
    <form method="post" action="{{ route('users.store') }}" class="card">
        @csrf
        <h2>Create User</h2>
        <div class="grid grid-2">
            <div class="field"><label>Full Name</label><input name="name" required></div>
            <div class="field"><label>Username</label><input name="username" required></div>
            <div class="field"><label>Email</label><input name="email" type="email" required></div>
            <div class="field"><label>Password</label><input name="password" type="password" required></div>
            <div class="field"><label>Role</label><select name="role_id" required>
                @foreach($roles as $role)
                    @if(auth()->user()->isSuperAdmin() || $role->name !== 'SUPER_ADMIN')
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endif
                @endforeach
            </select></div>
            <label style="display:flex;gap:8px;margin-top:25px"><input style="width:auto" type="checkbox" name="is_active" value="1" checked> Active user</label>
        </div>
        <button class="btn" style="margin-top:16px">Create User</button>
    </form>
    <section class="card">
        <h2>Access Roles</h2>
        @foreach($roles as $role)
            <p><strong>{{ $role->name }}</strong><br><span class="muted">{{ $role->description }}</span></p>
        @endforeach
    </section>
</div>
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Security</th><th>Update</th></tr></thead>
        <tbody>
        @foreach($users as $user)
            <tr>
                <td><strong>{{ $user->name }}</strong><br><span class="muted">{{ $user->username }} · {{ $user->email }}</span></td>
                <td>{{ $user->role?->name }}</td>
                <td><span class="badge">{{ $user->is_active ? 'ACTIVE' : 'DISABLED' }}</span></td>
                <td>{{ $user->must_change_password ? 'Password Reset Required' : 'Normal' }}</td>
                <td>
                    <form method="post" action="{{ route('users.update', $user) }}" style="display:grid;gap:8px;min-width:260px">
                        @csrf
                        @method('put')
                        <input name="name" value="{{ $user->name }}" required>
                        <input name="email" value="{{ $user->email }}" type="email" required>
                        <select name="role_id">
                            @foreach($roles as $role)
                                @if(auth()->user()->isSuperAdmin() || $role->name !== 'SUPER_ADMIN')
                                    <option value="{{ $role->id }}" @selected($role->id === $user->role_id)>{{ $role->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input name="password" type="password" placeholder="Optional new password">
                        <label style="display:flex;gap:8px"><input style="width:auto" type="checkbox" name="must_change_password" value="1" @checked($user->must_change_password)> Must change password</label>
                        <label style="display:flex;gap:8px"><input style="width:auto" type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active</label>
                        <button class="btn btn-dark">Update</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
<div style="margin-top:14px">{{ $users->links() }}</div>
@endsection
