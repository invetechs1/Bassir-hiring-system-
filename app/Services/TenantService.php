<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TenantService
{
    public function currentCompanyId(?User $user): ?int
    {
        return $user?->company_id;
    }

    public function scope(Builder $query, ?User $user, string $column = 'company_id'): Builder
    {
        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where($column, $user->company_id);
    }

    public function defaultCompanyId(?User $user): ?int
    {
        return $user?->company_id;
    }
}
