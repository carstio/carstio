<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\User\LastAdminException;
use App\Service\User\UserAccountManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAccountManagerTest extends TestCase
{
    public function testCreateHashesPasswordAndSetsRole(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');

        $manager = new UserAccountManager(
            $em,
            $this->createStub(UserRepository::class),
            $hasher,
        );

        $user = (new User())->setEmail('new@example.com')->setFullName('New');
        $manager->create($user, 'plain-password', UserRole::Mechanic);

        $this->assertContains('ROLE_MECHANIC', $user->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
        $this->assertSame('HASHED', $user->getPassword());
        $this->assertTrue($user->isActive());
    }

    public function testUpdateKeepsExistingPasswordWhenPlainPasswordIsEmpty(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');

        $manager = new UserAccountManager(
            $em,
            $this->createStub(UserRepository::class),
            $hasher,
        );

        $user = (new User())->setEmail('foo@example.com')->setFullName('Foo')->setPassword('OLDHASH');
        $user->setRoles(['ROLE_MECHANIC']);
        $user->setIsActive(true);

        $manager->update($user, null, UserRole::Mechanic, true);

        $this->assertSame('OLDHASH', $user->getPassword());
    }

    public function testUpdateHashesNewPasswordWhenProvided(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('NEWHASH');

        $manager = new UserAccountManager($em, $this->createStub(UserRepository::class), $hasher);

        $user = (new User())->setEmail('foo@example.com')->setFullName('Foo')->setPassword('OLDHASH');
        $user->setRoles(['ROLE_MECHANIC']);
        $user->setIsActive(true);

        $manager->update($user, 'newpass!!', UserRole::Mechanic, true);

        $this->assertSame('NEWHASH', $user->getPassword());
    }

    public function testDeactivateBlocksLastActiveAdmin(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('countActiveAdmins')->willReturn(0);

        $manager = new UserAccountManager(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(UserPasswordHasherInterface::class),
        );

        $user = (new User())->setEmail('a@a')->setFullName('A')->setIsActive(true);
        $user->setRoles(['ROLE_ADMIN']);

        $this->expectException(LastAdminException::class);
        $manager->deactivate($user);
    }

    public function testDeactivateAllowedWhenAnotherActiveAdminExists(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $repo = $this->createStub(UserRepository::class);
        $repo->method('countActiveAdmins')->willReturn(1);

        $manager = new UserAccountManager($em, $repo, $this->createStub(UserPasswordHasherInterface::class));

        $user = (new User())->setEmail('a@a')->setFullName('A')->setIsActive(true);
        $user->setRoles(['ROLE_ADMIN']);

        $manager->deactivate($user);
        $this->assertFalse($user->isActive());
    }

    public function testUpdateBlocksDemotionOfLastActiveAdmin(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('countActiveAdmins')->willReturn(0);

        $manager = new UserAccountManager(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(UserPasswordHasherInterface::class),
        );

        $user = (new User())->setEmail('a@a')->setFullName('A')->setIsActive(true);
        $user->setRoles(['ROLE_ADMIN']);

        $this->expectException(LastAdminException::class);
        $manager->update($user, null, UserRole::Mechanic, true);
    }

    public function testActivateHasNoLastAdminGuard(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $manager = new UserAccountManager($em, $this->createStub(UserRepository::class), $this->createStub(UserPasswordHasherInterface::class));

        $user = (new User())->setEmail('a@a')->setFullName('A')->setIsActive(false);
        $manager->activate($user);

        $this->assertTrue($user->isActive());
    }
}
