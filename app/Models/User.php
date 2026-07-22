<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['company_id', 'username', 'name', 'email', 'password', 'role_id', 'is_active', 'must_change_password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['is_active' => 'boolean', 'must_change_password' => 'boolean', 'password' => 'hashed'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mobileAccessTokens(): HasMany
    {
        return $this->hasMany(MobileAccessToken::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SUPER_ADMIN');
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) $this->role?->permissions?->contains('name', $permission);
    }
}
