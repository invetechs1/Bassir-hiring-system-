<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;
use PHPUnit\Framework\TestCase;

class TenantServiceTest extends TestCase
{
    public function test_default_company_id_comes_from_authenticated_user(): void
    {
        $user = new User(['company_id' => 42]);

        $this->assertSame(42, (new TenantService())->defaultCompanyId($user));
    }

    public function test_user_detects_super_admin_role(): void
    {
        $user = new User(['company_id' => 1]);
        $user->setRelation('role', new Role(['name' => 'SUPER_ADMIN']));

        $this->assertTrue($user->isSuperAdmin());
    }
}
