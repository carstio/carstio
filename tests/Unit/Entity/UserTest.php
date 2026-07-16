<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultUserAlwaysHasRoleUser(): void
    {
        $user = new User();

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testAdminUserHasAdminAndUserRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testMechanicUserHasMechanicAndUserRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_MECHANIC']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_MECHANIC', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testRolesAreDeduplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_MECHANIC', 'ROLE_MECHANIC']);

        $roles = array_values($user->getRoles());
        sort($roles);

        $this->assertSame(['ROLE_MECHANIC', 'ROLE_USER'], $roles);
    }
}
